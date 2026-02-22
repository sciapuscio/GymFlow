USE gymflow;

-- Fix: Ring Dips — muscle_group arms → chest
UPDATE exercises SET muscle_group='chest', tags_json='["chest","arms","stability"]' WHERE name='Ring Dips';

-- Fix: Wall Ball — level beginner → intermediate
UPDATE exercises SET level='intermediate', tags_json='["legs","shoulders","cardio"]' WHERE name='Wall Ball';

-- Fix: Kettlebell Swing — add hinge tag
UPDATE exercises SET tags_json='["full_body","cardio","power","hinge"]' WHERE name='Kettlebell Swing';

-- New exercises
INSERT IGNORE INTO exercises (gym_id, created_by, name, name_es, muscle_group, equipment, level, tags_json, duration_rec, is_global) VALUES
(NULL, NULL, 'Hip Thrust', 'Empuje de Cadera', 'glutes', '["barbell","bench"]', 'beginner', '["glutes","hinge","strength"]', 40, 1),
(NULL, NULL, 'Farmer Carry', 'Cargada del Granjero', 'full_body', '["kettlebell"]', 'intermediate', '["full_body","grip","carry","core"]', 30, 1),
(NULL, NULL, 'Hang Power Clean', 'Cargada de Potencia desde Colgado', 'full_body', '["barbell"]', 'intermediate', '["olympic","power","pull"]', 40, 1),
(NULL, NULL, 'Push Jerk', 'Push Jerk', 'shoulders', '["barbell"]', 'advanced', '["shoulders","legs","olympic","power"]', 35, 1);

-- Verify
SELECT name, muscle_group, level FROM exercises ORDER BY muscle_group, name;
