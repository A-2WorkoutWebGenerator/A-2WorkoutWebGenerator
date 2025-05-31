DROP SCHEMA IF EXISTS fitgen CASCADE;
CREATE SCHEMA fitgen;
SET search_path TO fitgen;
CREATE TYPE workout_type AS ENUM ('physiotherapy', 'kinetotherapy', 'sports');
CREATE TYPE difficulty_level AS ENUM ('beginner', 'intermediate', 'advanced', 'all_levels');
CREATE TYPE equipment_type AS ENUM ('none', 'basic', 'full');
CREATE TYPE activity_level AS ENUM ('sedentary', 'light', 'moderate', 'active');
CREATE TYPE fitness_goal AS ENUM ('lose_weight', 'build_muscle', 'flexibility', 'endurance', 'rehab');
CREATE TYPE gender_type AS ENUM ('male', 'female', 'other');

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS auth_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    CONSTRAINT fk_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_profiles (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    gender gender_type,
    age INTEGER CHECK (age >= 10 AND age <= 120),
    goal fitness_goal,
    activity_level activity_level,
    injuries TEXT,
    equipment equipment_type,
    profile_picture_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE exercise_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    workout_type workout_type NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE exercises (
    id SERIAL PRIMARY KEY,
    category_id INTEGER NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    duration_minutes INTEGER CHECK (duration_minutes > 0),
    difficulty difficulty_level NOT NULL,
    equipment_needed equipment_type DEFAULT 'none',
    video_url VARCHAR(500),
    image_url VARCHAR(500),
    muscle_groups TEXT[],
    calories_per_minute DECIMAL(4,2) DEFAULT 5.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES exercise_categories(id) ON DELETE CASCADE
);
CREATE TABLE workout_suggestions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    suggestion JSONB NOT NULL
);
CREATE TABLE workout_routines (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    workout_type workout_type NOT NULL,
    difficulty difficulty_level NOT NULL,
    duration_minutes INTEGER CHECK (duration_minutes > 0),
    frequency_per_week INTEGER CHECK (frequency_per_week >= 1 AND frequency_per_week <= 7),
    equipment_needed equipment_type DEFAULT 'none',
    created_by INTEGER, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE routine_exercises (
    id SERIAL PRIMARY KEY,
    routine_id INTEGER NOT NULL,
    exercise_id INTEGER NOT NULL,
    order_index INTEGER NOT NULL,
    sets INTEGER DEFAULT 1,
    reps INTEGER,
    duration_seconds INTEGER,
    rest_seconds INTEGER DEFAULT 30,
    FOREIGN KEY (routine_id) REFERENCES workout_routines(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    UNIQUE(routine_id, order_index)
);

CREATE TABLE user_saved_routines (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    routine_id INTEGER NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (routine_id) REFERENCES workout_routines(id) ON DELETE CASCADE,
    UNIQUE(user_id, routine_id)
);

CREATE TABLE workout_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    routine_id INTEGER,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    duration_minutes INTEGER,
    calories_burned INTEGER,
    user_rating INTEGER CHECK (user_rating >= 1 AND user_rating <= 5),
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (routine_id) REFERENCES workout_routines(id) ON DELETE SET NULL
);

CREATE TABLE session_exercises (
    id SERIAL PRIMARY KEY,
    session_id INTEGER NOT NULL,
    exercise_id INTEGER NOT NULL,
    sets_completed INTEGER DEFAULT 0,
    reps_completed INTEGER DEFAULT 0,
    duration_seconds INTEGER DEFAULT 0,
    calories_burned DECIMAL(6,2) DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (session_id) REFERENCES workout_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
);

CREATE TABLE user_stats (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    stat_date DATE DEFAULT CURRENT_DATE,
    total_workouts INTEGER DEFAULT 0,
    total_minutes INTEGER DEFAULT 0,
    total_calories INTEGER DEFAULT 0,
    current_streak INTEGER DEFAULT 0,
    longest_streak INTEGER DEFAULT 0,
    favorite_workout_type workout_type,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, stat_date)
);

CREATE TABLE audit_log (
    id SERIAL PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    operation VARCHAR(10) NOT NULL, 
    record_id INTEGER,
    user_id INTEGER,
    old_values JSONB,
    new_values JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address INET,
    user_agent TEXT
);

CREATE INDEX idx_user_profiles_user_id ON user_profiles(user_id);
CREATE INDEX idx_exercises_category_id ON exercises(category_id);
CREATE INDEX idx_exercises_difficulty ON exercises(difficulty);
CREATE INDEX idx_exercises_equipment ON exercises(equipment_needed);
CREATE INDEX idx_workout_routines_type ON workout_routines(workout_type);
CREATE INDEX idx_workout_routines_difficulty ON workout_routines(difficulty);
CREATE INDEX idx_user_saved_routines_user_id ON user_saved_routines(user_id);
CREATE INDEX idx_workout_sessions_user_id ON workout_sessions(user_id);
CREATE INDEX idx_workout_sessions_date ON workout_sessions(started_at);
CREATE INDEX idx_user_stats_user_id ON user_stats(user_id);
CREATE INDEX idx_user_stats_date ON user_stats(stat_date);
CREATE INDEX idx_audit_log_table_operation ON audit_log(table_name, operation);
CREATE INDEX idx_audit_log_created_at ON audit_log(created_at);

CREATE OR REPLACE FUNCTION update_modified_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE OR REPLACE FUNCTION calculate_age(birth_date DATE)
RETURNS INTEGER AS $$
BEGIN
    RETURN EXTRACT(YEAR FROM AGE(birth_date));
EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Error calculating age: %', SQLERRM;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION validate_email(email_address TEXT)
RETURNS BOOLEAN AS $$
BEGIN
    IF email_address IS NULL OR email_address = '' THEN
        RETURN FALSE;
    END IF;

    IF email_address ~ '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$' THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Email validation error: %', SQLERRM;
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE FUNCTION create_user_profile(
    p_user_id INTEGER,
    p_first_name VARCHAR(100),
    p_last_name VARCHAR(100),
    p_gender gender_type,
    p_age INTEGER,
    p_goal fitness_goal,
    p_activity_level activity_level,
    p_injuries TEXT DEFAULT NULL,
    p_equipment equipment_type DEFAULT 'none'
)
RETURNS TABLE(success BOOLEAN, message TEXT, profile_id INTEGER) AS $$
DECLARE
    v_profile_id INTEGER;
    v_existing_profile INTEGER;
BEGIN
    IF p_user_id IS NULL THEN
        RETURN QUERY SELECT FALSE, 'User ID cannot be null', NULL::INTEGER;
        RETURN;
    END IF;
    
    IF p_age IS NOT NULL AND (p_age < 10 OR p_age > 120) THEN
        RETURN QUERY SELECT FALSE, 'Age must be between 10 and 120', NULL::INTEGER;
        RETURN;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_user_id) THEN
        RETURN QUERY SELECT FALSE, 'User does not exist', NULL::INTEGER;
        RETURN;
    END IF;

    SELECT id INTO v_existing_profile 
    FROM user_profiles 
    WHERE user_id = p_user_id;
    
    IF v_existing_profile IS NOT NULL THEN
        UPDATE user_profiles SET
            first_name = p_first_name,
            last_name = p_last_name,
            gender = p_gender,
            age = p_age,
            goal = p_goal,
            activity_level = p_activity_level,
            injuries = p_injuries,
            equipment = p_equipment,
            updated_at = NOW()
        WHERE id = v_existing_profile;
        
        v_profile_id := v_existing_profile;
        
        RETURN QUERY SELECT TRUE, 'Profile updated successfully', v_profile_id;
    ELSE
        INSERT INTO user_profiles (
            user_id, first_name, last_name, gender, age, 
            goal, activity_level, injuries, equipment
        ) VALUES (
            p_user_id, p_first_name, p_last_name, p_gender, p_age,
            p_goal, p_activity_level, p_injuries, p_equipment
        ) RETURNING id INTO v_profile_id;

        INSERT INTO user_stats (user_id) VALUES (p_user_id);
        
        RETURN QUERY SELECT TRUE, 'Profile created successfully', v_profile_id;
    END IF;
    
EXCEPTION
    WHEN OTHERS THEN
        RETURN QUERY SELECT FALSE, 'Database error: ' || SQLERRM, NULL::INTEGER;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION get_workout_recommendations(
    p_user_id INTEGER,
    p_limit INTEGER DEFAULT 5
)
RETURNS TABLE(
    routine_id INTEGER,
    routine_name VARCHAR(200),
    description TEXT,
    workout_type workout_type,
    difficulty difficulty_level,
    duration_minutes INTEGER,
    match_score INTEGER
) AS $$
DECLARE
    v_user_goal fitness_goal;
    v_user_equipment equipment_type;
    v_user_activity activity_level;
    v_user_injuries TEXT;
BEGIN
    SELECT goal, equipment, activity_level, injuries
    INTO v_user_goal, v_user_equipment, v_user_activity, v_user_injuries
    FROM user_profiles
    WHERE user_id = p_user_id;
    
    IF NOT FOUND THEN
        RAISE EXCEPTION 'User profile not found for user_id: %', p_user_id;
    END IF;
    
    RETURN QUERY
    SELECT 
        wr.id,
        wr.name,
        wr.description,
        wr.workout_type,
        wr.difficulty,
        wr.duration_minutes,
        (
            CASE WHEN wr.equipment_needed <= v_user_equipment THEN 30 ELSE 0 END +
            CASE WHEN wr.workout_type = 'rehab' AND v_user_injuries IS NOT NULL THEN 25 ELSE 0 END +
            CASE WHEN wr.workout_type = 'physiotherapy' AND v_user_goal = 'rehab' THEN 20 ELSE 0 END +
            CASE WHEN wr.workout_type = 'sports' AND v_user_goal = 'build_muscle' THEN 15 ELSE 0 END +
            CASE 
                WHEN v_user_activity = 'sedentary' AND wr.difficulty = 'beginner' THEN 15
                WHEN v_user_activity = 'light' AND wr.difficulty IN ('beginner', 'intermediate') THEN 15
                WHEN v_user_activity = 'moderate' AND wr.difficulty IN ('intermediate', 'advanced') THEN 15
                WHEN v_user_activity = 'active' AND wr.difficulty = 'advanced' THEN 15
                ELSE 5
            END +
            CASE WHEN wr.duration_minutes BETWEEN 15 AND 45 THEN 10 ELSE 5 END
        ) AS score
    FROM workout_routines wr
    WHERE wr.equipment_needed <= v_user_equipment
    ORDER BY score DESC, wr.created_at DESC
    LIMIT p_limit;
    
EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Error generating recommendations: %', SQLERRM;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION calculate_user_statistics(p_user_id INTEGER)
RETURNS TABLE(
    total_workouts BIGINT,
    total_minutes BIGINT,
    total_calories BIGINT,
    current_streak INTEGER,
    longest_streak INTEGER,
    avg_rating NUMERIC,
    favorite_workout_type workout_type,
    workouts_this_month BIGINT,
    workouts_this_week BIGINT
) AS $$
DECLARE
    v_current_streak INTEGER := 0;
    v_longest_streak INTEGER := 0;
    v_streak_count INTEGER := 0;
    v_check_date DATE;
    v_favorite_type workout_type;
BEGIN
    SELECT 
        COUNT(*),
        COALESCE(SUM(duration_minutes), 0),
        COALESCE(SUM(calories_burned), 0),
        COALESCE(AVG(user_rating), 0)
    INTO total_workouts, total_minutes, total_calories, avg_rating
    FROM workout_sessions
    WHERE user_id = p_user_id AND completed_at IS NOT NULL;
    SELECT COUNT(*)
    INTO workouts_this_month
    FROM workout_sessions
    WHERE user_id = p_user_id 
        AND completed_at IS NOT NULL
        AND DATE_TRUNC('month', completed_at) = DATE_TRUNC('month', CURRENT_DATE);

    SELECT COUNT(*)
    INTO workouts_this_week
    FROM workout_sessions
    WHERE user_id = p_user_id 
        AND completed_at IS NOT NULL
        AND DATE_TRUNC('week', completed_at) = DATE_TRUNC('week', CURRENT_DATE);
    
    v_check_date := CURRENT_DATE;
    LOOP
        IF EXISTS (
            SELECT 1 FROM workout_sessions 
            WHERE user_id = p_user_id 
                AND DATE(completed_at) = v_check_date
                AND completed_at IS NOT NULL
        ) THEN
            v_streak_count := v_streak_count + 1;
            v_check_date := v_check_date - INTERVAL '1 day';
        ELSE
            EXIT;
        END IF;
    END LOOP;
    
    current_streak := v_streak_count;

    longest_streak := GREATEST(current_streak, v_streak_count);
    
    SELECT wr.workout_type
    INTO favorite_workout_type
    FROM workout_sessions ws
    JOIN workout_routines wr ON ws.routine_id = wr.id
    WHERE ws.user_id = p_user_id AND ws.completed_at IS NOT NULL
    GROUP BY wr.workout_type
    ORDER BY COUNT(*) DESC
    LIMIT 1;
    
    RETURN QUERY SELECT 
        calculate_user_statistics.total_workouts,
        calculate_user_statistics.total_minutes,
        calculate_user_statistics.total_calories,
        calculate_user_statistics.current_streak,
        calculate_user_statistics.longest_streak,
        calculate_user_statistics.avg_rating,
        calculate_user_statistics.favorite_workout_type,
        calculate_user_statistics.workouts_this_month,
        calculate_user_statistics.workouts_this_week;
    
EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Error calculating user statistics: %', SQLERRM;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION get_user_leaderboard(
    p_period VARCHAR(10) DEFAULT 'month', 
    p_metric VARCHAR(20) DEFAULT 'workouts',
    p_limit INTEGER DEFAULT 10
)
RETURNS TABLE(
    rank INTEGER,
    user_id INTEGER,
    username VARCHAR(50),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    metric_value BIGINT
) AS $$
DECLARE
    v_date_filter TEXT;
    v_metric_column TEXT;
    v_query TEXT;
BEGIN
    IF p_period NOT IN ('week', 'month', 'year', 'all') THEN
        RAISE EXCEPTION 'Invalid period. Must be: week, month, year, all';
    END IF;
    
    IF p_metric NOT IN ('workouts', 'minutes', 'calories') THEN
        RAISE EXCEPTION 'Invalid metric. Must be: workouts, minutes, calories';
    END IF;
    CASE p_period
        WHEN 'week' THEN v_date_filter := 'AND DATE_TRUNC(''week'', ws.completed_at) = DATE_TRUNC(''week'', CURRENT_DATE)';
        WHEN 'month' THEN v_date_filter := 'AND DATE_TRUNC(''month'', ws.completed_at) = DATE_TRUNC(''month'', CURRENT_DATE)';
        WHEN 'year' THEN v_date_filter := 'AND DATE_TRUNC(''year'', ws.completed_at) = DATE_TRUNC(''year'', CURRENT_DATE)';
        ELSE v_date_filter := '';
    END CASE;
    
    CASE p_metric
        WHEN 'workouts' THEN v_metric_column := 'COUNT(*)';
        WHEN 'minutes' THEN v_metric_column := 'COALESCE(SUM(ws.duration_minutes), 0)';
        WHEN 'calories' THEN v_metric_column := 'COALESCE(SUM(ws.calories_burned), 0)';
    END CASE;
    
    v_query := FORMAT('
        SELECT 
            ROW_NUMBER() OVER (ORDER BY %s DESC) as rank,
            u.id,
            u.username,
            COALESCE(up.first_name, '''') as first_name,
            COALESCE(up.last_name, '''') as last_name,
            %s as metric_value
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN workout_sessions ws ON u.id = ws.user_id AND ws.completed_at IS NOT NULL %s
        GROUP BY u.id, u.username, up.first_name, up.last_name
        HAVING %s > 0
        ORDER BY metric_value DESC
        LIMIT %s',
        v_metric_column, v_metric_column, v_date_filter, v_metric_column, p_limit
    );
    
    RETURN QUERY EXECUTE v_query;
    
EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Error generating leaderboard: %', SQLERRM;
END;
$$ LANGUAGE plpgsql;
CREATE OR REPLACE FUNCTION save_user_routine(
    p_user_id INTEGER,
    p_routine_id INTEGER,
    p_notes TEXT DEFAULT NULL
)
RETURNS TABLE(success BOOLEAN, message TEXT) AS $$
BEGIN

    IF p_user_id IS NULL OR p_routine_id IS NULL THEN
        RETURN QUERY SELECT FALSE, 'User ID and Routine ID cannot be null';
        RETURN;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_user_id) THEN
        RETURN QUERY SELECT FALSE, 'User does not exist';
        RETURN;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM workout_routines WHERE id = p_routine_id) THEN
        RETURN QUERY SELECT FALSE, 'Workout routine does not exist';
        RETURN;
    END IF;

    BEGIN
        INSERT INTO user_saved_routines (user_id, routine_id, notes)
        VALUES (p_user_id, p_routine_id, p_notes);
        
        RETURN QUERY SELECT TRUE, 'Routine saved successfully';
        
    EXCEPTION
        WHEN unique_violation THEN
            RETURN QUERY SELECT FALSE, 'Routine already saved by this user';
        WHEN OTHERS THEN
            RETURN QUERY SELECT FALSE, 'Error saving routine: ' || SQLERRM;
    END;
    
END;
$$ LANGUAGE plpgsql;