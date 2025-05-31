CREATE TABLE user_workouts (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    workout JSONB NOT NULL
);

CREATE OR REPLACE FUNCTION fitgen.generate_workout_for_user(
    p_user_id INTEGER,
    p_muscle_group TEXT DEFAULT NULL,
    p_difficulty fitgen.difficulty_level DEFAULT NULL,
    p_equipment fitgen.equipment_type DEFAULT NULL,
    p_num_exercises INTEGER DEFAULT 6
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
    muscle_groups TEXT[]
) AS $$
DECLARE
    v_selected_exercises RECORD;
    v_suggestion_id INTEGER;
    v_json JSONB;
BEGIN
    RETURN QUERY
    SELECT 
        e.id, e.name, e.description, e.instructions, e.duration_minutes,
        e.difficulty, e.equipment_needed, e.video_url, e.image_url, e.muscle_groups
    FROM fitgen.exercises e
    WHERE
        (p_muscle_group IS NULL OR p_muscle_group = '' OR 
            EXISTS (
                SELECT 1 FROM unnest(e.muscle_groups) mg WHERE mg = p_muscle_group
            )
        )
        AND (p_difficulty IS NULL OR e.difficulty = p_difficulty OR p_difficulty = 'all_levels')
        AND (p_equipment IS NULL OR e.equipment_needed = p_equipment)
    ORDER BY random()
    LIMIT p_num_exercises;
END;
$$ LANGUAGE plpgsql;