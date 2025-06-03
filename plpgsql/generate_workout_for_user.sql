SET search_path TO fitgen;

UPDATE fitgen.exercises
SET muscle_groups = array_remove(muscle_groups, 'full body')
WHERE 'full body' = ANY(muscle_groups);

DROP FUNCTION generate_workout_for_user(integer,text,difficulty_level,equipment_type,integer);

SET search_path TO fitgen;
TRUNCATE TABLE fitgen.exercises RESTART IDENTITY CASCADE;
SELECT * FROM fitgen.exercises;
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
    -- Target groups pentru "full body"
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
END;
$$ LANGUAGE plpgsql;