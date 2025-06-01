SET search_path TO fitgen;

UPDATE fitgen.exercises
SET muscle_groups = array_remove(muscle_groups, 'full body')
WHERE 'full body' = ANY(muscle_groups);

DROP FUNCTION generate_workout_for_user(integer,text,difficulty_level,equipment_type,integer);

CREATE OR REPLACE FUNCTION fitgen.generate_workout_for_user(
    p_user_id INTEGER,
    p_muscle_group TEXT DEFAULT NULL,
    p_difficulty fitgen.difficulty_level DEFAULT NULL,
    p_equipment fitgen.equipment_type DEFAULT NULL,
    p_total_duration INTEGER DEFAULT NULL
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
            (
                p_muscle_group IS NULL OR p_muscle_group = '' OR 
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

    -- Dacă nu s-a atins limita, adaugă un exercițiu mic (excludem deja selectate)
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
                (
                    p_muscle_group IS NULL OR p_muscle_group = '' OR 
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