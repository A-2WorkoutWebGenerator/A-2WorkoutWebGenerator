SET search_path TO fitgen;

CREATE TRIGGER update_users_modtime
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_modified_column();

CREATE TRIGGER update_user_profiles_modtime
    BEFORE UPDATE ON user_profiles
    FOR EACH ROW
    EXECUTE FUNCTION update_modified_column();

CREATE TRIGGER update_exercises_modtime
    BEFORE UPDATE ON exercises
    FOR EACH ROW
    EXECUTE FUNCTION update_modified_column();

CREATE TRIGGER update_workout_routines_modtime
    BEFORE UPDATE ON workout_routines
    FOR EACH ROW
    EXECUTE FUNCTION update_modified_column();

CREATE TRIGGER update_user_stats_modtime
    BEFORE UPDATE ON user_stats
    FOR EACH ROW
    EXECUTE FUNCTION update_modified_column();

CREATE OR REPLACE FUNCTION audit_trigger_function()
RETURNS TRIGGER AS $$
DECLARE
    v_user_id INTEGER := NULL;
    v_ip_address INET := NULL;
BEGIN

    BEGIN
        IF TG_TABLE_NAME = 'users' THEN
            IF TG_OP = 'DELETE' THEN
                v_user_id := OLD.id;
            ELSE
                v_user_id := NEW.id;
            END IF;
        ELSIF TG_TABLE_NAME = 'user_profiles' THEN
            IF TG_OP = 'DELETE' THEN
                v_user_id := OLD.user_id;
            ELSE
                v_user_id := NEW.user_id;
            END IF;
        ELSE
            v_user_id := NULL;
        END IF;
    EXCEPTION
        WHEN OTHERS THEN
            v_user_id := NULL;
    END;

    BEGIN
        v_ip_address := current_setting('app.client_ip')::INET;
    EXCEPTION
        WHEN OTHERS THEN
            v_ip_address := NULL;
    END;

    IF TG_OP = 'DELETE' THEN
        INSERT INTO audit_log (
            table_name, operation, record_id, user_id, 
            old_values, ip_address, created_at
        ) VALUES (
            TG_TABLE_NAME, TG_OP, 
            CASE WHEN TG_TABLE_NAME = 'users' THEN OLD.id ELSE OLD.id END,
            v_user_id, row_to_json(OLD), v_ip_address, NOW()
        );
        RETURN OLD;
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO audit_log (
            table_name, operation, record_id, user_id,
            old_values, new_values, ip_address, created_at
        ) VALUES (
            TG_TABLE_NAME, TG_OP,
            CASE WHEN TG_TABLE_NAME = 'users' THEN NEW.id ELSE NEW.id END,
            v_user_id, row_to_json(OLD), row_to_json(NEW), v_ip_address, NOW()
        );
        RETURN NEW;
    ELSIF TG_OP = 'INSERT' THEN
        INSERT INTO audit_log (
            table_name, operation, record_id, user_id,
            new_values, ip_address, created_at
        ) VALUES (
            TG_TABLE_NAME, TG_OP,
            CASE WHEN TG_TABLE_NAME = 'users' THEN NEW.id ELSE NEW.id END,
            v_user_id, row_to_json(NEW), v_ip_address, NOW()
        );
        RETURN NEW;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER audit_users_trigger
    AFTER INSERT OR UPDATE OR DELETE ON users
    FOR EACH ROW EXECUTE FUNCTION audit_trigger_function();

CREATE TRIGGER audit_user_profiles_trigger
    AFTER INSERT OR UPDATE OR DELETE ON user_profiles
    FOR EACH ROW EXECUTE FUNCTION audit_trigger_function();

CREATE TRIGGER audit_user_saved_routines_trigger
    AFTER INSERT OR UPDATE OR DELETE ON user_saved_routines
    FOR EACH ROW EXECUTE FUNCTION audit_trigger_function();

CREATE TRIGGER audit_workout_sessions_trigger
    AFTER INSERT OR UPDATE OR DELETE ON workout_sessions
    FOR EACH ROW EXECUTE FUNCTION audit_trigger_function();

CREATE TRIGGER audit_exercises_trigger
    AFTER INSERT OR UPDATE OR DELETE ON exercises
    FOR EACH ROW EXECUTE FUNCTION audit_trigger_function();

CREATE OR REPLACE FUNCTION validate_user_profile()
RETURNS TRIGGER AS $$
BEGIN

    IF NEW.age IS NOT NULL AND (NEW.age < 10 OR NEW.age > 120) THEN
        RAISE EXCEPTION 'Age must be between 10 and 120 years. Provided: %', NEW.age;
    END IF;

    IF NEW.first_name IS NOT NULL AND trim(NEW.first_name) = '' THEN
        RAISE EXCEPTION 'First name cannot be empty or only whitespace';
    END IF;
    
    IF NEW.last_name IS NOT NULL AND trim(NEW.last_name) = '' THEN
        RAISE EXCEPTION 'Last name cannot be empty or only whitespace';
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER validate_user_profile_trigger
    BEFORE INSERT OR UPDATE ON user_profiles
    FOR EACH ROW EXECUTE FUNCTION validate_user_profile();

CREATE OR REPLACE FUNCTION validate_user()
RETURNS TRIGGER AS $$
BEGIN

    IF NOT validate_email(NEW.email) THEN
        RAISE EXCEPTION 'Invalid email format: %', NEW.email;
    END IF;

    IF length(trim(NEW.username)) < 3 THEN
        RAISE EXCEPTION 'Username must be at least 3 characters long';
    END IF;
    
    IF NEW.username ~ '\s' THEN
        RAISE EXCEPTION 'Username cannot contain spaces';
    END IF;

    NEW.email := lower(NEW.email);
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER validate_user_trigger
    BEFORE INSERT OR UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION validate_user();

CREATE OR REPLACE FUNCTION update_user_stats_on_workout()
RETURNS TRIGGER AS $$
DECLARE
    v_stats_record user_stats%ROWTYPE;
    v_current_date DATE := CURRENT_DATE;
BEGIN

    IF TG_OP = 'UPDATE' AND OLD.completed_at IS NULL AND NEW.completed_at IS NOT NULL THEN

        SELECT * INTO v_stats_record
        FROM user_stats
        WHERE user_id = NEW.user_id AND stat_date = v_current_date;
        
        IF FOUND THEN

            UPDATE user_stats SET
                total_workouts = total_workouts + 1,
                total_minutes = total_minutes + COALESCE(NEW.duration_minutes, 0),
                total_calories = total_calories + COALESCE(NEW.calories_burned, 0),
                updated_at = NOW()
            WHERE user_id = NEW.user_id AND stat_date = v_current_date;
        ELSE

            INSERT INTO user_stats (
                user_id, stat_date, total_workouts, total_minutes, total_calories
            ) VALUES (
                NEW.user_id, v_current_date, 1, 
                COALESCE(NEW.duration_minutes, 0), 
                COALESCE(NEW.calories_burned, 0)
            );
        END IF;

        PERFORM update_user_streak(NEW.user_id);
        
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION update_user_streak(p_user_id INTEGER)
RETURNS VOID AS $$
DECLARE
    v_yesterday_workout BOOLEAN;
    v_current_streak INTEGER := 1;
BEGIN

    SELECT EXISTS(
        SELECT 1 FROM workout_sessions 
        WHERE user_id = p_user_id 
            AND DATE(completed_at) = CURRENT_DATE - INTERVAL '1 day'
            AND completed_at IS NOT NULL
    ) INTO v_yesterday_workout;
    
    IF v_yesterday_workout THEN

        SELECT COALESCE(current_streak, 0) + 1 INTO v_current_streak
        FROM user_stats 
        WHERE user_id = p_user_id AND stat_date = CURRENT_DATE - INTERVAL '1 day';
    END IF;

    UPDATE user_stats SET
        current_streak = v_current_streak,
        longest_streak = GREATEST(longest_streak, v_current_streak)
    WHERE user_id = p_user_id AND stat_date = CURRENT_DATE;
    
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_stats_on_workout_completion
    AFTER UPDATE ON workout_sessions
    FOR EACH ROW EXECUTE FUNCTION update_user_stats_on_workout();

CREATE OR REPLACE FUNCTION register_user(
    p_username VARCHAR(50),
    p_email VARCHAR(100),
    p_password VARCHAR(255)
)
RETURNS TABLE(success BOOLEAN, message TEXT, user_id INTEGER) AS $$
DECLARE
    v_user_id INTEGER;
BEGIN

    IF p_username IS NULL OR trim(p_username) = '' THEN
        RETURN QUERY SELECT FALSE, 'Username cannot be empty', NULL::INTEGER;
        RETURN;
    END IF;
    
    IF p_email IS NULL OR trim(p_email) = '' THEN
        RETURN QUERY SELECT FALSE, 'Email cannot be empty', NULL::INTEGER;
        RETURN;
    END IF;
    
    IF p_password IS NULL OR length(p_password) < 8 THEN
        RETURN QUERY SELECT FALSE, 'Password must be at least 8 characters long', NULL::INTEGER;
        RETURN;
    END IF;

    BEGIN
        INSERT INTO users (username, email, password)
        VALUES (trim(p_username), lower(trim(p_email)), p_password)
        RETURNING id INTO v_user_id;
        
        RETURN QUERY SELECT TRUE, 'User registered successfully', v_user_id;
        
    EXCEPTION
        WHEN unique_violation THEN

            IF POSITION('username' IN SQLERRM) > 0 THEN
                RETURN QUERY SELECT FALSE, 'Username already exists', NULL::INTEGER;
            ELSE
                RETURN QUERY SELECT FALSE, 'Email already exists', NULL::INTEGER;
            END IF;
        WHEN OTHERS THEN
            RETURN QUERY SELECT FALSE, 'Registration failed: ' || SQLERRM, NULL::INTEGER;
    END;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION authenticate_user(
    p_username VARCHAR(50),
    p_password VARCHAR(255)
)
RETURNS TABLE(
    success BOOLEAN, 
    message TEXT, 
    user_id INTEGER,
    username VARCHAR(50),
    email VARCHAR(100)
) AS $$
DECLARE
    v_user_record users%ROWTYPE;
BEGIN
    SELECT * INTO v_user_record
    FROM users
    WHERE username = p_username;
    
    IF NOT FOUND THEN
        RETURN QUERY SELECT FALSE, 'Invalid username or password', 
                           NULL::INTEGER, NULL::VARCHAR(50), NULL::VARCHAR(100);
        RETURN;
    END IF;
    
    RETURN QUERY SELECT TRUE, 'Authentication successful',
                       v_user_record.id, v_user_record.username, v_user_record.email;
    
EXCEPTION
    WHEN OTHERS THEN
        RETURN QUERY SELECT FALSE, 'Authentication error: ' || SQLERRM,
                           NULL::INTEGER, NULL::VARCHAR(50), NULL::VARCHAR(100);
END;
$$ LANGUAGE plpgsql;
