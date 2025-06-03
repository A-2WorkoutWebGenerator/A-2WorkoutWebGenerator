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
        v_user_id := current_setting('app.current_user_id')::INTEGER;
    EXCEPTION
        WHEN OTHERS THEN

            IF TG_OP = 'DELETE' THEN
                IF OLD.user_id IS NOT NULL THEN
                    v_user_id := OLD.user_id;
                ELSIF TG_TABLE_NAME = 'users' THEN
                    v_user_id := OLD.id;
                END IF;
            ELSE
                IF NEW.user_id IS NOT NULL THEN
                    v_user_id := NEW.user_id;
                ELSIF TG_TABLE_NAME = 'users' THEN
                    v_user_id := NEW.id;
                END IF;
            END IF;
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

CREATE OR REPLACE FUNCTION populate_sample_data()
RETURNS TEXT AS $$
DECLARE
    v_result TEXT := '';
    v_category_id INTEGER;
    v_exercise_id INTEGER;
    v_routine_id INTEGER;
    v_user_id INTEGER;
    i INTEGER;
BEGIN
    v_result := 'Starting database population...' || E'\n';

    v_result := v_result || 'Inserting exercise categories...' || E'\n';
    
    INSERT INTO exercise_categories (name, description, workout_type) VALUES
    ('Lower Back Pain Relief', 'Exercises for alleviating lower back pain', 'physiotherapy'),
    ('Shoulder Mobility', 'Exercises to improve shoulder range of motion', 'physiotherapy'),
    ('Knee Rehabilitation', 'Strengthening exercises for knee recovery', 'physiotherapy'),
    ('Full-Body Stretching', 'Complete flexibility routine', 'physiotherapy'),
    ('Postural Correction', 'Exercises to correct posture problems', 'kinetotherapy'),
    ('Core Stabilization', 'Core strengthening for stability', 'kinetotherapy'),
    ('Functional Mobility', 'Daily movement pattern improvement', 'kinetotherapy'),
    ('Neuromuscular Coordination', 'Brain-muscle connection exercises', 'kinetotherapy'),
    ('Football Training', 'Sport-specific football exercises', 'sports'),
    ('Basketball Drills', 'Basketball skill development', 'sports'),
    ('Tennis Conditioning', 'Tennis-specific conditioning', 'sports'),
    ('Swimming Technique', 'Swimming stroke improvement', 'sports');
    
    v_result := v_result || 'Inserting exercises...' || E'\n';

    SELECT id INTO v_category_id FROM exercise_categories WHERE name = 'Lower Back Pain Relief';
    INSERT INTO exercises (category_id, name, description, instructions, duration_minutes, difficulty, equipment_needed, muscle_groups, calories_per_minute) VALUES
    (v_category_id, 'Pelvic Tilts', 'Gentle exercise to strengthen core and relieve back tension', 'Lie on back, tilt pelvis upward', 15, 'beginner', 'none', ARRAY['core', 'lower_back'], 3.0),
    (v_category_id, 'Knee-to-Chest Stretch', 'Stretches lower back muscles', 'Pull knees to chest while lying down', 10, 'beginner', 'none', ARRAY['lower_back', 'glutes'], 2.5),
    (v_category_id, 'Cat-Cow Stretch', 'Improves spine flexibility', 'Alternate between arching and rounding spine', 10, 'beginner', 'none', ARRAY['spine', 'core'], 3.5),
    (v_category_id, 'Bridge Exercise', 'Strengthens glutes and lower back', 'Lift hips while lying on back', 12, 'intermediate', 'none', ARRAY['glutes', 'lower_back', 'core'], 4.0);
    
    SELECT id INTO v_category_id FROM exercise_categories WHERE name = 'Shoulder Mobility';
    INSERT INTO exercises (category_id, name, description, instructions, duration_minutes, difficulty, equipment_needed, muscle_groups, calories_per_minute) VALUES
    (v_category_id, 'Pendulum Swings', 'Gentle shoulder mobility exercise', 'Let arm hang and swing in circles', 8, 'beginner', 'none', ARRAY['shoulders'], 2.0),
    (v_category_id, 'Wall Slides', 'Improves shoulder blade movement', 'Slide arms up and down against wall', 10, 'intermediate', 'none', ARRAY['shoulders', 'upper_back'], 3.0),
    (v_category_id, 'External Rotations', 'Strengthens rotator cuff', 'Rotate arm outward with resistance', 12, 'intermediate', 'basic', ARRAY['shoulders', 'rotator_cuff'], 3.5);

    SELECT id INTO v_category_id FROM exercise_categories WHERE name = 'Postural Correction';
    INSERT INTO exercises (category_id, name, description, instructions, duration_minutes, difficulty, equipment_needed, muscle_groups, calories_per_minute) VALUES
    (v_category_id, 'Wall Posture Alignment', 'Teaches proper standing posture', 'Stand against wall with proper alignment', 15, 'beginner', 'none', ARRAY['core', 'upper_back'], 2.5),
    (v_category_id, 'Scapular Retraction', 'Strengthens upper back muscles', 'Squeeze shoulder blades together', 10, 'beginner', 'none', ARRAY['upper_back', 'shoulders'], 3.0),
    (v_category_id, 'Chin Tucks', 'Corrects forward head posture', 'Pull chin back to align head over shoulders', 8, 'beginner', 'none', ARRAY['neck', 'upper_back'], 2.0);

    v_result := v_result || 'Creating workout routines...' || E'\n';

    INSERT INTO workout_routines (name, description, workout_type, difficulty, duration_minutes, frequency_per_week, equipment_needed)
    VALUES ('Lower Back Pain Relief Program', 'Gentle exercises designed to alleviate lower back pain and improve spine mobility', 'physiotherapy', 'all_levels', 20, 5, 'none')
    RETURNING id INTO v_routine_id;

    INSERT INTO routine_exercises (routine_id, exercise_id, order_index, sets, reps, duration_seconds, rest_seconds)
    SELECT v_routine_id, e.id, ROW_NUMBER() OVER (ORDER BY e.id), 2, 10, 30, 15
    FROM exercises e
    JOIN exercise_categories ec ON e.category_id = ec.id
    WHERE ec.name = 'Lower Back Pain Relief';

    INSERT INTO workout_routines (name, description, workout_type, difficulty, duration_minutes, frequency_per_week, equipment_needed)
    VALUES ('Postural Correction Routine', 'Correct posture problems and alignment issues', 'kinetotherapy', 'beginner', 18, 4, 'none')
    RETURNING id INTO v_routine_id;
    
    INSERT INTO routine_exercises (routine_id, exercise_id, order_index, sets, reps, duration_seconds, rest_seconds)
    SELECT v_routine_id, e.id, ROW_NUMBER() OVER (ORDER BY e.id), 3, 15, 45, 20
    FROM exercises e
    JOIN exercise_categories ec ON e.category_id = ec.id
    WHERE ec.name = 'Postural Correction';

    v_result := v_result || 'Creating test users...' || E'\n';
    
    FOR i IN 1..5 LOOP
        SELECT result.user_id INTO v_user_id
        FROM register_user(
            'testuser' || i,
            'test' || i || '@fitgen.com',
            '$2y$10$example.hash.for.user' || i
        ) AS result
        WHERE result.success = TRUE;
        
        IF v_user_id IS NOT NULL THEN
            PERFORM create_user_profile(
                v_user_id,
                'Test' || i,
                'User' || i,
                CASE i % 3 WHEN 0 THEN 'male' WHEN 1 THEN 'female' ELSE 'other' END,
                20 + (i * 5),
                CASE i % 5 WHEN 0 THEN 'lose_weight' WHEN 1 THEN 'build_muscle' WHEN 2 THEN 'flexibility' WHEN 3 THEN 'endurance' ELSE 'rehab' END,
                CASE i % 4 WHEN 0 THEN 'sedentary' WHEN 1 THEN 'light' WHEN 2 THEN 'moderate' ELSE 'active' END,
                CASE WHEN i % 2 = 0 THEN 'Previous knee injury' ELSE NULL END,
                CASE i % 3 WHEN 0 THEN 'none' WHEN 1 THEN 'basic' ELSE 'full' END
            );

            PERFORM save_user_routine(v_user_id, 1, 'Great for my back pain');
            IF i % 2 = 0 THEN
                PERFORM save_user_routine(v_user_id, 2, 'Helping with posture');
            END IF;
        END IF;
    END LOOP;
    
    v_result := v_result || 'Database population completed successfully!' || E'\n';
    v_result := v_result || 'Created: ' || (SELECT COUNT(*) FROM exercise_categories) || ' exercise categories' || E'\n';
    v_result := v_result || 'Created: ' || (SELECT COUNT(*) FROM exercises) || ' exercises' || E'\n';
    v_result := v_result || 'Created: ' || (SELECT COUNT(*) FROM workout_routines) || ' workout routines' || E'\n';
    v_result := v_result || 'Created: ' || (SELECT COUNT(*) FROM users) || ' test users' || E'\n';
    v_result := v_result || 'Created: ' || (SELECT COUNT(*) FROM user_profiles) || ' user profiles' || E'\n';
    
    RETURN v_result;
    
EXCEPTION
    WHEN OTHERS THEN
        RETURN 'Error during population: ' || SQLERRM;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION generate_user_report(p_user_id INTEGER)
RETURNS JSON AS $$
DECLARE
    v_report JSON;
    v_user_info JSON;
    v_stats JSON;
    v_recommendations JSON;
BEGIN

    SELECT row_to_json(user_data) INTO v_user_info
    FROM (
        SELECT u.username, u.email, up.first_name, up.last_name, 
               up.age, up.goal, up.activity_level, up.equipment
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id = p_user_id
    ) user_data;

    SELECT row_to_json(stats_data) INTO v_stats
    FROM (
        SELECT * FROM calculate_user_statistics(p_user_id)
    ) stats_data;

    SELECT json_agg(rec_data) INTO v_recommendations
    FROM (
        SELECT * FROM get_workout_recommendations(p_user_id, 3)
    ) rec_data;

    v_report := json_build_object(
        'user_info', v_user_info,
        'statistics', v_stats,
        'recommendations', v_recommendations,
        'generated_at', NOW()
    );
    
    RETURN v_report;
    
EXCEPTION
    WHEN OTHERS THEN
        RETURN json_build_object('error', 'Failed to generate report: ' || SQLERRM);
END;
$$ LANGUAGE plpgsql;

SELECT populate_sample_data();

CREATE VIEW routine_details AS
SELECT 
    wr.id as routine_id,
    wr.name as routine_name,
    wr.description,
    wr.workout_type,
    wr.difficulty,
    wr.duration_minutes,
    wr.frequency_per_week,
    wr.equipment_needed,
    json_agg(
        json_build_object(
            'exercise_name', e.name,
            'exercise_description', e.description,
            'order_index', re.order_index,
            'sets', re.sets,
            'reps', re.reps,
            'duration_seconds', re.duration_seconds,
            'muscle_groups', e.muscle_groups
        ) ORDER BY re.order_index
    ) as exercises
FROM workout_routines wr
LEFT JOIN routine_exercises re ON wr.id = re.routine_id
LEFT JOIN exercises e ON re.exercise_id = e.id
GROUP BY wr.id, wr.name, wr.description, wr.workout_type, wr.difficulty, 
         wr.duration_minutes, wr.frequency_per_week, wr.equipment_needed;

CREATE VIEW user_dashboard AS
SELECT 
    u.id as user_id,
    u.username,
    up.first_name,
    up.last_name,
    up.goal,
    up.activity_level,
    us.total_workouts,
    us.total_minutes,
    us.total_calories,
    us.current_streak,
    us.longest_streak,
    (SELECT COUNT(*) FROM user_saved_routines usr WHERE usr.user_id = u.id) as saved_routines_count,
    (SELECT COUNT(*) FROM workout_sessions ws WHERE ws.user_id = u.id AND ws.completed_at IS NOT NULL) as completed_sessions
FROM users u
LEFT JOIN user_profiles up ON u.id = up.user_id
LEFT JOIN user_stats us ON u.id = us.user_id AND us.stat_date = CURRENT_DATE;

COMMENT ON SCHEMA fitgen IS 'FitGen Application Database Schema with PL/pgSQL Implementation';
COMMENT ON FUNCTION register_user IS 'Registers a new user with validation and exception handling';
COMMENT ON FUNCTION create_user_profile IS 'Creates or updates user profile with comprehensive validation';
COMMENT ON FUNCTION get_workout_recommendations IS 'Generates personalized workout recommendations based on user profile';
COMMENT ON FUNCTION calculate_user_statistics IS 'Calculates comprehensive user statistics including streaks';
COMMENT ON FUNCTION get_user_leaderboard IS 'Generates leaderboard rankings for different metrics and time periods';

SELECT 'FitGen PL/pgSQL implementation completed successfully!' as status,
       'All tables, functions, triggers, and sample data have been created.' as details;