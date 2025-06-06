DROP SCHEMA IF EXISTS fitgen CASCADE;
CREATE SCHEMA fitgen;
SET search_path TO fitgen;
CREATE TYPE workout_type AS ENUM ('physiotherapy', 'kinetotherapy', 'sports');
CREATE TYPE difficulty_level AS ENUM ('beginner', 'intermediate', 'advanced', 'all_levels');
CREATE TYPE equipment_type AS ENUM ('none', 'basic', 'full');
CREATE TYPE activity_level AS ENUM ('sedentary', 'light', 'moderate', 'active');
CREATE TYPE fitness_goal AS ENUM ('lose_weight', 'build_muscle', 'flexibility', 'endurance', 'rehab', 'mobility', 'posture', 'strength', 'cardio');
CREATE TYPE gender_type AS ENUM ('male', 'female', 'other');

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);
ALTER TABLE fitgen.users ADD COLUMN isAdmin BOOLEAN DEFAULT FALSE;
UPDATE fitgen.users SET isAdmin = TRUE WHERE username = 'aramaAndreea';
SET search_path TO fitgen;
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
ALTER TABLE fitgen.exercises ADD COLUMN location TEXT;
ALTER TABLE fitgen.exercises ADD COLUMN min_age INTEGER;
ALTER TABLE fitgen.exercises ADD COLUMN max_age INTEGER;
ALTER TABLE fitgen.exercises ADD COLUMN gender TEXT;
ALTER TABLE fitgen.exercises ADD COLUMN min_weight FLOAT;
ALTER TABLE fitgen.exercises ADD COLUMN goal TEXT;
ALTER TABLE fitgen.exercises ADD COLUMN contraindications TEXT;

INSERT INTO fitgen.exercises (category_id, name, description, instructions, duration_minutes, difficulty, equipment_needed, video_url, image_url, muscle_groups, calories_per_minute, created_at, updated_at, location, min_age, max_age, gender, min_weight, goal, contraindications) VALUES
(1, 'Pelvic Tilts', 'Gentle exercise to strengthen core and relieve back tension. No equipment needed. Main muscle group: core.', 'Lie on back, tilt pelvis upward', 10, 'beginner', 'none', NULL, NULL, ARRAY['core'], 4.50, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'rehab', 'Avoid if acute lower back pain or recent back surgery'),
(1, 'Knee-to-Chest Stretch', 'Stretches lower back muscles. No equipment needed. Main muscle group: lower body.', 'Pull knees to chest while lying down', 8, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 3.80, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'flexibility', 'Avoid if recent hip or back surgery'),
(1, 'Cat-Cow Stretch', 'Improves spine flexibility. No equipment needed. Main muscle group: core.', 'Alternate between arching and rounding spine', 8, 'beginner', 'none', NULL, NULL, ARRAY['core'], 3.50, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'mobility', NULL),
(1, 'Bridge Exercise', 'Strengthens glutes and lower back. No equipment needed. Main muscle group: lower body.', 'Lift hips while lying on back', 12, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 4.80, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'strength', 'Avoid if recent back or hip surgery'),
(2, 'Pendulum Swings', 'Gentle shoulder mobility exercise. No equipment needed. Main muscle group: upper body.', 'Let arm hang and swing in circles', 8, 'beginner', 'none', NULL, NULL, ARRAY['upper body'], 3.20, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'mobility', 'Avoid if acute shoulder injury'),
(2, 'Wall Slides', 'Improves shoulder blade movement. No equipment needed. Main muscle group: upper body.', 'Slide arms up and down against wall', 10, 'beginner', 'none', NULL, NULL, ARRAY['upper body'], 4.40, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'mobility', NULL),
(2, 'External Rotations', 'Strengthens rotator cuff. Requires a resistance band (basic equipment). Main muscle group: upper body.', 'Rotate arm outward with resistance', 10, 'beginner', 'basic', NULL, NULL, ARRAY['upper body'], 4.20, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 40, 'strength', 'Avoid if acute rotator cuff tear'),
(5, 'Wall Posture Alignment', 'Teaches proper standing posture. No equipment needed. Main muscle group: upper body.', 'Stand against wall with proper alignment', 10, 'beginner', 'none', NULL, NULL, ARRAY['upper body'], 3.00, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'posture', NULL),
(5, 'Scapular Retraction', 'Strengthens upper back muscles. No equipment needed. Main muscle group: upper body.', 'Squeeze shoulder blades together', 8, 'beginner', 'none', NULL, NULL, ARRAY['upper body'], 4.10, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'strength', NULL),
(5, 'Chin Tucks', 'Corrects forward head posture. No equipment needed. Main muscle group: upper body.', 'Pull chin back to align head over shoulders', 6, 'beginner', 'none', NULL, NULL, ARRAY['upper body'], 3.00, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'posture', NULL),
(3, 'Plank', 'Isometric core strength exercise. No equipment needed. Main muscle group: core.', 'Hold body in straight line, supported on forearms and toes', 30, 'intermediate', 'none', NULL, NULL, ARRAY['core'], 6.00, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 40, 'strength', 'Avoid if shoulder or core injury'),
(3, 'Side Plank', 'Targets obliques and stabilizers. No equipment needed. Main muscle group: core.', 'Lie on side, lift hips, support on forearm and foot', 20, 'intermediate', 'none', NULL, NULL, ARRAY['core'], 5.50, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 40, 'strength', 'Avoid if shoulder injury'),
(4, 'Glute Bridge', 'Activates glutes and hamstrings. No equipment needed. Main muscle group: lower body.', 'Lie on back, feet flat, lift hips', 15, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 5.20, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'strength', 'Avoid if back or hip pain'),
(4, 'Bird Dog', 'Improves core stability. No equipment needed. Main muscle group: core.', 'On all fours, extend opposite arm and leg', 12, 'beginner', 'none', NULL, NULL, ARRAY['core'], 5.00, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'mobility', NULL),
(1, 'Superman', 'Strengthens lower back. No equipment needed. Main muscle group: core.', 'Lie face down, lift arms and legs simultaneously', 10, 'beginner', 'none', NULL, NULL, ARRAY['core'], 5.10, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'strength', 'Avoid if acute back pain'),
(2, 'Shoulder Abduction', 'Strengthens deltoids. Requires a pair of dumbbells (basic equipment). Main muscle group: upper body.', 'Lift arm to side up to shoulder height', 10, 'beginner', 'basic', NULL, NULL, ARRAY['upper body'], 4.80, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 45, 'strength', 'Avoid if acute shoulder injury'),
(2, 'Shoulder Flexion', 'Improves shoulder mobility. Requires a pair of dumbbells (basic equipment). Main muscle group: upper body.', 'Lift arm forward up to shoulder height', 10, 'beginner', 'basic', NULL, NULL, ARRAY['upper body'], 4.80, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 45, 'mobility', 'Avoid if acute shoulder injury'),
(2, 'Reverse Fly', 'Strengthens rear shoulders. Requires a pair of dumbbells (basic equipment). Main muscle group: upper body.', 'Bend forward, raise arms to sides', 12, 'intermediate', 'basic', NULL, NULL, ARRAY['upper body'], 5.30, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 45, 'strength', 'Avoid if shoulder or back injury'),
(2, 'Banded Pull Apart', 'Strengthens upper back and shoulders. Requires a resistance band (basic equipment). Main muscle group: upper body.', 'Hold band at shoulder height, pull band apart', 10, 'beginner', 'basic', NULL, NULL, ARRAY['upper body'], 4.60, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 40, 'strength', NULL),
(5, 'Wall Angels', 'Improves posture and shoulder mobility. No equipment needed. Main muscle group: upper body.', 'Stand against wall, slide arms overhead', 8, 'beginner', 'none', NULL, NULL, ARRAY['upper body'], 4.90, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'mobility', NULL),
(6, 'Squat', 'Compound lower body movement. No equipment needed. Main muscle group: lower body.', 'Stand with feet shoulder-width, squat down and up', 15, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 7.00, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 40, 'strength', 'Avoid if knee pain'),
(6, 'Lunge', 'Strengthens legs and improves balance. No equipment needed. Main muscle group: lower body.', 'Step forward, lower back knee toward floor', 12, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 6.80, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 40, 'strength', 'Avoid if knee pain'),
(6, 'Step Up', 'Builds leg strength and balance. Requires a bench or sturdy platform (basic equipment). Main muscle group: lower body.', 'Step onto elevated surface and back down', 12, 'beginner', 'basic', NULL, NULL, ARRAY['lower body'], 6.50, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 40, 'strength', 'Avoid if knee instability'),
(6, 'Calf Raise', 'Strengthens calves. No equipment needed. Main muscle group: lower body.', 'Stand and rise onto toes', 8, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 4.70, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'strength', NULL),
(3, 'Dead Bug', 'Core stability exercise. No equipment needed. Main muscle group: core.', 'Lie on back, move opposite arm and leg away from body', 10, 'beginner', 'none', NULL, NULL, ARRAY['core'], 5.00, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'mobility', NULL),
(1, 'Child''s Pose Stretch', 'Stretches lower back and hips. No equipment needed. Main muscle groups: lower body, core.', 'Kneel and sit back on heels, reach arms forward', 8, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 3.50, '2025-06-01 14:15:06.514171', '2025-06-02 15:44:05.062293', 'home', 10, 99, NULL, 35, 'flexibility', 'Avoid if knee injury'),
(3, 'Mountain Climber', 'Dynamic core and cardio move. No equipment needed. Main muscle group: core.', 'Start in plank, alternate driving knees to chest', 10, 'intermediate', 'none', NULL, NULL, ARRAY['core'], 7.20, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 45, 'cardio', 'Avoid if wrist or shoulder pain'),
(3, 'Russian Twist', 'Targets obliques. No equipment needed. Main muscle group: core.', 'Sit, lean back, rotate torso side to side', 8, 'intermediate', 'none', NULL, NULL, ARRAY['core'], 6.30, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 45, 'strength', 'Avoid if lower back pain'),
(2, 'Band External Rotation', 'Rotator cuff strength exercise. Requires a resistance band (basic equipment). Main muscle group: upper body.', 'Attach band, keep elbow at side, rotate forearm out', 12, 'beginner', 'basic', NULL, NULL, ARRAY['upper body'], 4.70, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 40, 'strength', 'Avoid if acute shoulder injury'),
(5, 'Neck Stretch', 'Relieves neck tension. No equipment needed. Main muscle group: upper body.', 'Tilt head toward shoulder, hold, repeat both sides', 6, 'beginner', 'none', NULL, NULL, ARRAY['upper body'], 3.00, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'flexibility', NULL),
(5, 'Thoracic Extension', 'Improves upper back mobility. Requires a chair (basic equipment). Main muscle group: upper body.', 'Sit in chair, arch upper back over support', 10, 'beginner', 'basic', NULL, NULL, ARRAY['upper body'], 4.10, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'mobility', NULL),
(5, 'Standing Row', 'Strengthens upper back. Requires resistance band (basic equipment). Main muscle group: upper body.', 'Pull band or cable toward chest from standing', 12, 'beginner', 'basic', NULL, NULL, ARRAY['upper body'], 5.20, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 45, 'strength', NULL),
(6, 'Glute Kickback', 'Targets glutes and hamstrings. No equipment needed. Main muscle group: lower body.', 'On all fours, kick leg back and upward', 10, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 5.60, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 40, 'strength', NULL),
(6, 'Side Lying Leg Lift', 'Strengthens hip abductors. No equipment needed. Main muscle group: lower body.', 'Lie on side, lift top leg upward', 10, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 4.90, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'strength', NULL),
(6, 'Wall Sit', 'Isometric leg strength. No equipment needed. Main muscle group: lower body.', 'Lean against wall, squat and hold', 10, 'intermediate', 'none', NULL, NULL, ARRAY['lower body'], 6.20, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 14, 99, NULL, 40, 'strength', 'Avoid if knee pain'),
(3, 'V-Up', 'Advanced core exercise for abs. No equipment needed. Main muscle group: core.', 'Lie on back, lift arms and legs to touch above body', 8, 'advanced', 'none', NULL, NULL, ARRAY['core'], 7.20, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 16, 99, NULL, 50, 'strength', 'Avoid if lower back injury'),
(2, 'Shoulder Press', 'Strengthens shoulders. Requires dumbbells (basic equipment). Main muscle group: upper body.', 'Press dumbbells overhead while seated or standing', 10, 'intermediate', 'basic', NULL, NULL, ARRAY['upper body'], 6.10, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 16, 99, NULL, 50, 'strength', 'Avoid if shoulder pain'),
(3, 'Hollow Hold', 'Core endurance and stability. No equipment needed. Main muscle group: core.', 'Lie on back, lift legs and shoulders off floor', 12, 'intermediate', 'none', NULL, NULL, ARRAY['core'], 6.60, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 16, 99, NULL, 50, 'strength', 'Avoid if lower back pain'),
(5, 'Upper Trap Stretch', 'Stretches upper trapezius muscles. No equipment needed. Main muscle group: upper body.', 'Sit or stand, gently pull head to side', 6, 'beginner', 'none', NULL, NULL, ARRAY['upper body'], 3.20, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 10, 99, NULL, 35, 'flexibility', NULL),
(1, 'Seated Forward Fold', 'Stretches hamstrings and low back. No equipment needed. Main muscle group: lower body.', 'Sit with legs extended, reach toward toes', 10, 'beginner', 'none', NULL, NULL, ARRAY['lower body'], 3.60, '2025-06-01 14:15:06.514171', '2025-06-01 20:36:34.244269', 'home', 12, 99, NULL, 35, 'flexibility', 'Avoid if severe lower back pain');

/*CREATE TABLE workout_suggestions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    suggestion JSONB NOT NULL
);*/


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

CREATE INDEX idx_user_saved_routines_user_id ON user_saved_routines(user_id);

CREATE INDEX idx_user_stats_user_id ON user_stats(user_id);
CREATE INDEX idx_user_stats_date ON user_stats(stat_date);

CREATE INDEX idx_audit_log_table_operation ON audit_log(table_name, operation);
CREATE INDEX idx_audit_log_created_at ON audit_log(created_at);

CREATE TABLE success_stories (
    id SERIAL PRIMARY KEY,
    user_name VARCHAR(100) NOT NULL,
    achievement VARCHAR(255) NOT NULL,
    story_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_approved BOOLEAN DEFAULT TRUE,
    ip_address INET,
    user_agent TEXT
);

CREATE INDEX idx_success_stories_created_at ON success_stories(created_at DESC);
CREATE INDEX idx_success_stories_approved ON success_stories(is_approved);

COMMENT ON TABLE success_stories IS 'Stores user submitted success stories for the fitness app';
COMMENT ON COLUMN success_stories.user_name IS 'Name of the user sharing the story';
COMMENT ON COLUMN success_stories.achievement IS 'Brief description of what they achieved';
COMMENT ON COLUMN success_stories.story_text IS 'Full story text';
COMMENT ON COLUMN success_stories.is_approved IS 'Whether the story is approved for display';
COMMENT ON COLUMN success_stories.ip_address IS 'IP address for spam prevention';
COMMENT ON COLUMN success_stories.user_agent IS 'Browser info for analytics';

CREATE TABLE contact_messages (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    ip_address INET,
    user_agent TEXT,
    response_sent BOOLEAN DEFAULT FALSE,
    admin_notes TEXT
);

CREATE INDEX idx_contact_messages_created_at ON contact_messages(created_at DESC);
CREATE INDEX idx_contact_messages_read ON contact_messages(is_read);
CREATE INDEX idx_contact_messages_email ON contact_messages(email);

COMMENT ON TABLE contact_messages IS 'Stores contact form submissions from users';
COMMENT ON COLUMN contact_messages.full_name IS 'Full name of the person contacting';
COMMENT ON COLUMN contact_messages.email IS 'Email address for response';
COMMENT ON COLUMN contact_messages.message IS 'The actual message content';
COMMENT ON COLUMN contact_messages.is_read IS 'Whether admin has read this message';
COMMENT ON COLUMN contact_messages.response_sent IS 'Whether a response has been sent';
COMMENT ON COLUMN contact_messages.admin_notes IS 'Internal notes for admin use';

CREATE OR REPLACE FUNCTION update_modified_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
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

CREATE OR REPLACE FUNCTION calculate_age(birth_year INTEGER)
RETURNS INTEGER AS $$
BEGIN
    RETURN EXTRACT(YEAR FROM CURRENT_DATE) - birth_year;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fitgen.generate_workout_for_user(
    p_user_id INTEGER,
    p_muscle_group TEXT DEFAULT NULL,
    p_difficulty fitgen.difficulty_level DEFAULT NULL,
    p_equipment fitgen.equipment_type DEFAULT NULL,
    p_total_duration INTEGER DEFAULT NULL,
    p_location TEXT DEFAULT NULL,
    p_age INTEGER DEFAULT NULL,
    p_weight FLOAT DEFAULT NULL,
    p_goal TEXT DEFAULT NULL,
    p_injuries TEXT DEFAULT NULL
)
RETURNS TABLE(
    exercise_id INTEGER,
    name VARCHAR(200),
    description TEXT,
    instructions TEXT,
    duration_minutes INTEGER,
    difficulty fitgen.difficulty_level,
    equipment_needed fitgen.equipment_type,
    video_url VARCHAR(500),
    image_url VARCHAR(500),
    muscle_groups JSONB
) AS $$
DECLARE
    v_cur_duration INTEGER := 0;
    v_exercise RECORD;
    v_selected_ids INTEGER[] := '{}';
    v_target_groups TEXT[];
BEGIN
    IF p_muscle_group IS NOT NULL AND REGEXP_REPLACE(LOWER(p_muscle_group), '\s+', '', 'g') = 'fullbody' THEN
        v_target_groups := ARRAY['core', 'upper body', 'lower body'];
    ELSE
        v_target_groups := ARRAY[p_muscle_group];
    END IF;

    FOR v_exercise IN
        SELECT 
            e.id, 
            e.name, 
            e.description, 
            e.instructions, 
            e.duration_minutes,
            e.difficulty, 
            e.equipment_needed, 
            e.video_url, 
            e.image_url, 
            to_jsonb(e.muscle_groups) AS muscle_groups
        FROM fitgen.exercises e
        WHERE
            (p_muscle_group IS NULL OR p_muscle_group = '' OR 
                EXISTS (
                    SELECT 1 FROM unnest(e.muscle_groups) mg 
                    WHERE REGEXP_REPLACE(LOWER(mg), '\s+', '', 'g') = ANY(
                        ARRAY(
                            SELECT REGEXP_REPLACE(LOWER(g), '\s+', '', 'g') FROM unnest(v_target_groups) g
                        )
                    )
                )
            )
            AND (p_difficulty IS NULL OR e.difficulty = p_difficulty OR e.difficulty = 'all_levels')
            AND (p_equipment IS NULL OR e.equipment_needed = p_equipment)
            AND (p_location IS NULL OR p_location = '' OR e.location IS NULL OR LOWER(e.location) = LOWER(p_location))
            AND (p_age IS NULL OR (e.min_age IS NULL OR p_age >= e.min_age) AND (e.max_age IS NULL OR p_age <= e.max_age))
            AND (p_weight IS NULL OR e.min_weight IS NULL OR p_weight >= e.min_weight)
            AND (p_goal IS NULL OR p_goal = '' OR e.goal IS NULL OR LOWER(e.goal) = LOWER(p_goal))
            AND (p_injuries IS NULL OR p_injuries = '' OR e.contraindications IS NULL OR e.contraindications NOT ILIKE '%' || p_injuries || '%')
        ORDER BY e.duration_minutes DESC, random()
    LOOP
        IF NOT v_exercise.id = ANY(v_selected_ids)
           AND (p_total_duration IS NULL OR v_cur_duration + v_exercise.duration_minutes <= p_total_duration) THEN
            v_selected_ids := array_append(v_selected_ids, v_exercise.id);
            v_cur_duration := v_cur_duration + v_exercise.duration_minutes;

            exercise_id      := v_exercise.id;
            name             := v_exercise.name;
            description      := v_exercise.description;
            instructions     := v_exercise.instructions;
            duration_minutes := v_exercise.duration_minutes;
            difficulty       := v_exercise.difficulty;
            equipment_needed := v_exercise.equipment_needed;
            video_url        := v_exercise.video_url;
            image_url        := v_exercise.image_url;
            muscle_groups    := v_exercise.muscle_groups;
            RETURN NEXT;
        END IF;
        IF p_total_duration IS NOT NULL AND v_cur_duration >= p_total_duration THEN
            EXIT;
        END IF;
    END LOOP;

    IF p_total_duration IS NOT NULL AND v_cur_duration < p_total_duration THEN
        FOR v_exercise IN
            SELECT 
                e.id, 
                e.name, 
                e.description, 
                e.instructions, 
                e.duration_minutes,
                e.difficulty, 
                e.equipment_needed, 
                e.video_url, 
                e.image_url, 
                to_jsonb(e.muscle_groups) AS muscle_groups
            FROM fitgen.exercises e
            WHERE
                (p_muscle_group IS NULL OR p_muscle_group = '' OR 
                    EXISTS (
                        SELECT 1 FROM unnest(e.muscle_groups) mg 
                        WHERE REGEXP_REPLACE(LOWER(mg), '\s+', '', 'g') = ANY(
                            ARRAY(
                                SELECT REGEXP_REPLACE(LOWER(g), '\s+', '', 'g') FROM unnest(v_target_groups) g
                            )
                        )
                    )
                )
                AND (p_difficulty IS NULL OR e.difficulty = p_difficulty OR e.difficulty = 'all_levels')
                AND (p_equipment IS NULL OR e.equipment_needed = p_equipment)
                AND (p_location IS NULL OR p_location = '' OR e.location IS NULL OR LOWER(e.location) = LOWER(p_location))
                AND (p_age IS NULL OR (e.min_age IS NULL OR p_age >= e.min_age) AND (e.max_age IS NULL OR p_age <= e.max_age))
                AND (p_weight IS NULL OR e.min_weight IS NULL OR p_weight >= e.min_weight)
                AND (p_goal IS NULL OR p_goal = '' OR e.goal IS NULL OR LOWER(e.goal) = LOWER(p_goal))
                AND (p_injuries IS NULL OR p_injuries = '' OR e.contraindications IS NULL OR e.contraindications NOT ILIKE '%' || p_injuries || '%')
                AND NOT e.id = ANY(v_selected_ids)
            ORDER BY e.duration_minutes ASC, random()
            LIMIT 1
        LOOP
            exercise_id      := v_exercise.id;
            name             := v_exercise.name;
            description      := v_exercise.description;
            instructions     := v_exercise.instructions;
            duration_minutes := v_exercise.duration_minutes;
            difficulty       := v_exercise.difficulty;
            equipment_needed := v_exercise.equipment_needed;
            video_url        := v_exercise.video_url;
            image_url        := v_exercise.image_url;
            muscle_groups    := v_exercise.muscle_groups;
            RETURN NEXT;
            EXIT;
        END LOOP;
    END IF;
    EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'generate_workout_for_user error: %', SQLERRM;
        RETURN;
END;
$$ LANGUAGE plpgsql;



CREATE OR REPLACE FUNCTION fitgen.get_champions_leaderboard(
    p_age_group TEXT DEFAULT NULL,
    p_gender TEXT DEFAULT NULL,
    p_goal TEXT DEFAULT NULL,
    p_limit INTEGER DEFAULT 50
)
RETURNS TABLE(
    user_id INTEGER,
    username TEXT,
    first_name TEXT,
    last_name TEXT,
    age INTEGER,
    gender TEXT,
    goal TEXT,
    profile_picture_path TEXT,
    total_workouts BIGINT,
    active_days BIGINT,
    total_duration NUMERIC,
    activity_score NUMERIC,
    rank_position BIGINT
) AS $$
BEGIN
    RETURN QUERY
    WITH user_stats AS (
        SELECT 
            u.id::INTEGER as user_id,
            u.username::TEXT,
            COALESCE(up.first_name, '')::TEXT as first_name,
            COALESCE(up.last_name, '')::TEXT as last_name,
            up.age::INTEGER,
            CASE 
                WHEN up.gender IS NOT NULL THEN up.gender::TEXT
                ELSE NULL
            END as gender,
            CASE 
                WHEN up.goal IS NOT NULL THEN up.goal::TEXT
                ELSE NULL
            END as goal,
            COALESCE(up.profile_picture_path, '')::TEXT as profile_picture_path,
            COUNT(uw.id) as total_workouts,
            CASE 
                WHEN COUNT(uw.id) > 0 THEN COUNT(DISTINCT DATE(uw.generated_at))
                ELSE 0::BIGINT
            END as active_days,
            COALESCE(
                SUM(
                    CASE 
                        WHEN uw.workout IS NOT NULL AND jsonb_typeof(uw.workout) = 'array' THEN 
                            (
                                SELECT SUM(
                                    CASE 
                                        WHEN exercise ? 'duration_minutes' 
                                        AND exercise->>'duration_minutes' ~ '^[0-9]+(\.[0-9]+)?$'
                                        THEN (exercise->>'duration_minutes')::NUMERIC
                                        ELSE 0
                                    END
                                )
                                FROM jsonb_array_elements(uw.workout) as exercise
                            )
                        ELSE 0
                    END
                ), 0
            ) as total_duration
        FROM fitgen.users u
        LEFT JOIN fitgen.user_profiles up ON u.id = up.user_id
        LEFT JOIN fitgen.user_workouts uw ON u.id = uw.user_id
        WHERE 
            (p_age_group IS NULL OR p_age_group = '' OR 
             CASE p_age_group
                WHEN 'youth' THEN up.age BETWEEN 12 AND 25
                WHEN 'adult' THEN up.age BETWEEN 26 AND 45  
                WHEN 'senior' THEN up.age > 45
                ELSE TRUE
             END)
            AND (p_gender IS NULL OR p_gender = '' OR up.gender::TEXT = p_gender)
            AND (p_goal IS NULL OR p_goal = '' OR up.goal::TEXT = p_goal)
        GROUP BY u.id, u.username, up.first_name, up.last_name, up.age, up.gender, up.goal, up.profile_picture_path
        HAVING COUNT(uw.id) > 0
    ),
    ranked_users AS (
        SELECT 
            us.*,
            (us.total_workouts * 10 + us.active_days * 15 + us.total_duration * 0.5) as activity_score,
            ROW_NUMBER() OVER (
                ORDER BY (us.total_workouts * 10 + us.active_days * 15 + us.total_duration * 0.5) DESC
            ) as rank_position
        FROM user_stats us
    )
    SELECT 
        ru.user_id,
        ru.username,
        ru.first_name,
        ru.last_name,
        ru.age,
        COALESCE(ru.gender, '') as gender,
        COALESCE(ru.goal, '') as goal,
        ru.profile_picture_path,
        ru.total_workouts,
        ru.active_days,
        ru.total_duration,
        ROUND(ru.activity_score, 2) as activity_score,
        ru.rank_position
    FROM ranked_users ru
    ORDER BY ru.activity_score DESC
    LIMIT COALESCE(p_limit, 50);
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fitgen.get_champions_stats()
RETURNS TABLE(
    total_active_users INTEGER,
    total_workouts_generated BIGINT,
    total_workout_minutes NUMERIC,
    average_workouts_per_user NUMERIC,
    most_active_age_group TEXT,
    most_popular_goal TEXT
) AS $$
BEGIN
    RETURN QUERY
    WITH stats AS (
        SELECT 
            COUNT(DISTINCT u.id)::INTEGER as active_users,
            COUNT(uw.id) as total_workouts,
            COALESCE(
                SUM(
                    CASE 
                        WHEN uw.workout IS NOT NULL AND jsonb_typeof(uw.workout) = 'array' THEN 
                            (
                                SELECT SUM(
                                    CASE 
                                        WHEN exercise ? 'duration_minutes' 
                                        AND exercise->>'duration_minutes' ~ '^[0-9]+(\.[0-9]+)?$'
                                        THEN (exercise->>'duration_minutes')::NUMERIC
                                        ELSE 0
                                    END
                                )
                                FROM jsonb_array_elements(uw.workout) as exercise
                            )
                        ELSE 0
                    END
                ), 0
            ) as total_minutes
        FROM fitgen.users u
        LEFT JOIN fitgen.user_profiles up ON u.id = up.user_id
        LEFT JOIN fitgen.user_workouts uw ON u.id = uw.user_id
        WHERE uw.id IS NOT NULL
    ),
    age_groups AS (
        SELECT 
            CASE 
                WHEN up.age BETWEEN 12 AND 25 THEN 'youth'
                WHEN up.age BETWEEN 26 AND 45 THEN 'adult'
                WHEN up.age > 45 THEN 'senior'
                ELSE 'unknown'
            END as age_group,
            COUNT(*) as group_count
        FROM fitgen.user_profiles up
        JOIN fitgen.user_workouts uw ON up.user_id = uw.user_id
        WHERE up.age IS NOT NULL
        GROUP BY 
            CASE 
                WHEN up.age BETWEEN 12 AND 25 THEN 'youth'
                WHEN up.age BETWEEN 26 AND 45 THEN 'adult'
                WHEN up.age > 45 THEN 'senior'
                ELSE 'unknown'
            END
        ORDER BY group_count DESC
        LIMIT 1
    ),
    popular_goals AS (
        SELECT up.goal::TEXT as goal, COUNT(*) as goal_count
        FROM fitgen.user_profiles up
        JOIN fitgen.user_workouts uw ON up.user_id = uw.user_id
        WHERE up.goal IS NOT NULL
        GROUP BY up.goal::TEXT
        ORDER BY goal_count DESC
        LIMIT 1
    )
    SELECT 
        COALESCE(s.active_users, 0)::INTEGER,
        COALESCE(s.total_workouts, 0),
        COALESCE(s.total_minutes, 0),
        CASE 
            WHEN s.active_users > 0 THEN ROUND(s.total_workouts::NUMERIC / s.active_users, 2)
            ELSE 0::NUMERIC
        END as avg_workouts,
        COALESCE(ag.age_group, 'unknown')::TEXT as most_active_age,
        COALESCE(pg.goal, 'unknown')::TEXT as most_popular_goal
    FROM stats s
    LEFT JOIN age_groups ag ON true
    LEFT JOIN popular_goals pg ON true;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE VIEW fitgen.champions_quick_stats AS
SELECT 
    u.id,
    u.username,
    COALESCE(up.first_name, '') as first_name,
    COALESCE(up.last_name, '') as last_name,
    COUNT(uw.id) as workout_count,
    COUNT(DISTINCT DATE(uw.generated_at)) as active_days,
    MAX(uw.generated_at) as last_workout,
    (COUNT(uw.id) * 10 + COUNT(DISTINCT DATE(uw.generated_at)) * 15) as base_score
FROM fitgen.users u
LEFT JOIN fitgen.user_profiles up ON u.id = up.user_id
LEFT JOIN fitgen.user_workouts uw ON u.id = uw.user_id
WHERE uw.id IS NOT NULL
GROUP BY u.id, u.username, up.first_name, up.last_name
ORDER BY base_score DESC;



CREATE OR REPLACE FUNCTION fitgen.get_user_statistics(p_user_id INTEGER)
RETURNS TABLE(
    total_workouts BIGINT,
    this_month_workouts BIGINT,
    this_week_workouts BIGINT,
    total_exercises BIGINT,
    total_duration_minutes NUMERIC,
    avg_workout_duration NUMERIC,
    most_popular_muscle_group TEXT,
    most_used_difficulty TEXT,
    most_used_equipment TEXT,
    workout_streak_days INTEGER,
    last_workout_date TIMESTAMP,
    monthly_chart_data JSONB,
    muscle_group_stats JSONB,
    difficulty_stats JSONB,
    recent_workouts JSONB
) AS $$
DECLARE
    v_result RECORD;
BEGIN
    SELECT 
        COUNT(uw.id) as total_workouts,
        COUNT(CASE WHEN uw.generated_at >= date_trunc('month', CURRENT_DATE) THEN 1 END) as this_month_workouts,
        COUNT(CASE WHEN uw.generated_at >= date_trunc('week', CURRENT_DATE) THEN 1 END) as this_week_workouts,
        
        COALESCE(SUM(
            CASE 
                WHEN uw.workout IS NOT NULL AND jsonb_typeof(uw.workout) = 'array' 
                THEN jsonb_array_length(uw.workout)
                ELSE 0
            END
        ), 0) as total_exercises,

        COALESCE(SUM(
            CASE 
                WHEN uw.workout IS NOT NULL AND jsonb_typeof(uw.workout) = 'array' THEN 
                    (
                        SELECT SUM(
                            CASE 
                                WHEN exercise ? 'duration_minutes' 
                                AND exercise->>'duration_minutes' ~ '^[0-9]+(\.[0-9]+)?$'
                                THEN (exercise->>'duration_minutes')::NUMERIC
                                ELSE 0
                            END
                        )
                        FROM jsonb_array_elements(uw.workout) as exercise
                    )
                ELSE 0
            END
        ), 0) as total_duration,
        
        MAX(uw.generated_at) as last_workout
        
    INTO v_result
    FROM fitgen.user_workouts uw
    WHERE uw.user_id = p_user_id;
    
    total_workouts := COALESCE(v_result.total_workouts, 0);
    this_month_workouts := COALESCE(v_result.this_month_workouts, 0);
    this_week_workouts := COALESCE(v_result.this_week_workouts, 0);
    total_exercises := COALESCE(v_result.total_exercises, 0);
    total_duration_minutes := COALESCE(v_result.total_duration, 0);
    last_workout_date := v_result.last_workout;
    
    IF total_workouts > 0 THEN
        avg_workout_duration := ROUND(total_duration_minutes / total_workouts, 1);
    ELSE
        avg_workout_duration := 0;
    END IF;

    SELECT muscle_group INTO most_popular_muscle_group
    FROM (
        SELECT 
            jsonb_array_elements_text(exercise->'muscle_groups') as muscle_group,
            COUNT(*) as usage_count
        FROM fitgen.user_workouts uw,
             jsonb_array_elements(uw.workout) as exercise
        WHERE uw.user_id = p_user_id
        AND exercise ? 'muscle_groups'
        AND jsonb_typeof(exercise->'muscle_groups') = 'array'
        GROUP BY muscle_group
        ORDER BY usage_count DESC
        LIMIT 1
    ) popular_muscle;
    
    SELECT difficulty INTO most_used_difficulty
    FROM (
        SELECT 
            exercise->>'difficulty' as difficulty,
            COUNT(*) as usage_count
        FROM fitgen.user_workouts uw,
             jsonb_array_elements(uw.workout) as exercise
        WHERE uw.user_id = p_user_id
        AND exercise ? 'difficulty'
        AND exercise->>'difficulty' IS NOT NULL
        GROUP BY difficulty
        ORDER BY usage_count DESC
        LIMIT 1
    ) popular_difficulty;
    
    SELECT equipment INTO most_used_equipment
    FROM (
        SELECT 
            exercise->>'equipment_needed' as equipment,
            COUNT(*) as usage_count
        FROM fitgen.user_workouts uw,
             jsonb_array_elements(uw.workout) as exercise
        WHERE uw.user_id = p_user_id
        AND exercise ? 'equipment_needed'
        AND exercise->>'equipment_needed' IS NOT NULL
        GROUP BY equipment
        ORDER BY usage_count DESC
        LIMIT 1
    ) popular_equipment;
    
    WITH workout_dates AS (
        SELECT DISTINCT DATE(generated_at) as workout_date
        FROM fitgen.user_workouts
        WHERE user_id = p_user_id
        ORDER BY workout_date DESC
    ),
    date_gaps AS (
        SELECT 
            workout_date,
            workout_date - LAG(workout_date, 1, workout_date) OVER (ORDER BY workout_date DESC) as gap
        FROM workout_dates
    ),
    streak_groups AS (
        SELECT 
            workout_date,
            SUM(CASE WHEN gap > 1 THEN 1 ELSE 0 END) OVER (ORDER BY workout_date DESC) as streak_group
        FROM date_gaps
    )
    SELECT COUNT(*) INTO workout_streak_days
    FROM streak_groups
    WHERE streak_group = 0;
    
    workout_streak_days := COALESCE(workout_streak_days, 0);
    
    SELECT jsonb_agg(
        jsonb_build_object(
            'month', month_name,
            'workouts', workout_count
        ) ORDER BY month_date
    ) INTO monthly_chart_data
    FROM (
        SELECT 
            TO_CHAR(month_date, 'Mon YYYY') as month_name,
            month_date,
            COALESCE(workout_count, 0) as workout_count
        FROM (
            SELECT generate_series(
                date_trunc('month', CURRENT_DATE - INTERVAL '11 months'),
                date_trunc('month', CURRENT_DATE),
                '1 month'::INTERVAL
            ) as month_date
        ) months
        LEFT JOIN (
            SELECT 
                date_trunc('month', generated_at) as month,
                COUNT(*) as workout_count
            FROM fitgen.user_workouts
            WHERE user_id = p_user_id
            AND generated_at >= date_trunc('month', CURRENT_DATE - INTERVAL '11 months')
            GROUP BY date_trunc('month', generated_at)
        ) monthly_stats ON months.month_date = monthly_stats.month
        ORDER BY month_date
    ) monthly_data;
    
    SELECT jsonb_agg(
        jsonb_build_object(
            'muscle_group', muscle_group,
            'count', usage_count,
            'percentage', ROUND((usage_count * 100.0 / total_exercises), 1)
        ) ORDER BY usage_count DESC
    ) INTO muscle_group_stats
    FROM (
        SELECT 
            jsonb_array_elements_text(exercise->'muscle_groups') as muscle_group,
            COUNT(*) as usage_count
        FROM fitgen.user_workouts uw,
             jsonb_array_elements(uw.workout) as exercise
        WHERE uw.user_id = p_user_id
        AND exercise ? 'muscle_groups'
        AND jsonb_typeof(exercise->'muscle_groups') = 'array'
        GROUP BY muscle_group
        ORDER BY usage_count DESC
        LIMIT 8
    ) muscle_stats;
    
    SELECT jsonb_agg(
        jsonb_build_object(
            'difficulty', difficulty,
            'count', usage_count,
            'percentage', ROUND((usage_count * 100.0 / total_exercises), 1)
        ) ORDER BY usage_count DESC
    ) INTO difficulty_stats
    FROM (
        SELECT 
            exercise->>'difficulty' as difficulty,
            COUNT(*) as usage_count
        FROM fitgen.user_workouts uw,
             jsonb_array_elements(uw.workout) as exercise
        WHERE uw.user_id = p_user_id
        AND exercise ? 'difficulty'
        AND exercise->>'difficulty' IS NOT NULL
        GROUP BY difficulty
        ORDER BY usage_count DESC
    ) diff_stats;
    SELECT jsonb_agg(
        jsonb_build_object(
            'date', TO_CHAR(generated_at, 'DD Mon YYYY'),
            'exercises_count', jsonb_array_length(workout),
            'total_duration', (
                SELECT SUM(
                    CASE 
                        WHEN exercise ? 'duration_minutes' 
                        AND exercise->>'duration_minutes' ~ '^[0-9]+(\.[0-9]+)?$'
                        THEN (exercise->>'duration_minutes')::NUMERIC
                        ELSE 0
                    END
                )
                FROM jsonb_array_elements(workout) as exercise
            ),
            'exercises', (
                SELECT jsonb_agg(
                    jsonb_build_object(
                        'name', exercise->>'name',
                        'duration', exercise->>'duration_minutes',
                        'difficulty', exercise->>'difficulty'
                    )
                )
                FROM jsonb_array_elements(workout) as exercise
            )
        ) ORDER BY generated_at DESC
    ) INTO recent_workouts
    FROM (
        SELECT workout, generated_at
        FROM fitgen.user_workouts
        WHERE user_id = p_user_id
        ORDER BY generated_at DESC
        LIMIT 5
    ) recent;
    
    most_popular_muscle_group := COALESCE(most_popular_muscle_group, 'N/A');
    most_used_difficulty := COALESCE(most_used_difficulty, 'N/A');
    most_used_equipment := COALESCE(most_used_equipment, 'N/A');
    monthly_chart_data := COALESCE(monthly_chart_data, '[]'::jsonb);
    muscle_group_stats := COALESCE(muscle_group_stats, '[]'::jsonb);
    difficulty_stats := COALESCE(difficulty_stats, '[]'::jsonb);
    recent_workouts := COALESCE(recent_workouts, '[]'::jsonb);
    
    RETURN NEXT;
END;
$$ LANGUAGE plpgsql;

