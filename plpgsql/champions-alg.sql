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