USE gymflow;

-- ============================================================
-- GYMS
-- ============================================================

INSERT INTO gyms (name, slug, primary_color, secondary_color, font_family, font_display, spotify_mode, active) VALUES
('CrossFit Palermo', 'crossfit-palermo', '#00f5d4', '#ff6b35', 'Inter', 'Bebas Neue', 'instructor', 1),
('Iron Gym', 'iron-gym', '#f59e0b', '#ef4444', 'Inter', 'Bebas Neue', 'disabled', 1);

-- ============================================================
-- SALAS
-- ============================================================

INSERT INTO salas (gym_id, name, display_code, active) VALUES
(1, 'Sala Principal', 'CF-MAIN', 1),
(1, 'Sala HIIT', 'CF-HIIT', 1),
(2, 'Box Central', 'IRON-BOX', 1);

INSERT INTO sync_state (sala_id) VALUES (1), (2), (3);

-- ============================================================
-- USERS  (password: Admin123!)
-- hash = password_hash('Admin123!', PASSWORD_BCRYPT)
-- ============================================================

INSERT INTO users (gym_id, name, email, password_hash, role) VALUES
(NULL, 'Super Admin', 'superadmin@gymflow.app', '$2y$10$QieGsTbS6m357CkGm408x.D/nIN46FKMhbHZMJZZiedVP5Cf4i2a2', 'superadmin'),
(1, 'Admin CrossFit', 'admin@crossfitpalermo.com', '$2y$10$QieGsTbS6m357CkGm408x.D/nIN46FKMhbHZMJZZiedVP5Cf4i2a2', 'admin'),
(1, 'Juan Martínez', 'juan@crossfitpalermo.com', '$2y$10$QieGsTbS6m357CkGm408x.D/nIN46FKMhbHZMJZZiedVP5Cf4i2a2', 'instructor'),
(1, 'María López', 'maria@crossfitpalermo.com', '$2y$10$QieGsTbS6m357CkGm408x.D/nIN46FKMhbHZMJZZiedVP5Cf4i2a2', 'instructor'),
(2, 'Admin Iron', 'admin@irongym.com', '$2y$10$QieGsTbS6m357CkGm408x.D/nIN46FKMhbHZMJZZiedVP5Cf4i2a2', 'admin');

INSERT INTO instructor_profiles (user_id) VALUES (3), (4);

-- ============================================================
-- EXERCISES (Global library + Gym-specific)
-- ============================================================

INSERT INTO exercises (gym_id, created_by, name, name_es, muscle_group, equipment, level, tags_json, duration_rec, is_global) VALUES
-- Cardio / Full Body
(NULL, NULL, 'Box Jumps', 'Saltos al Cajón', 'legs', '["box"]', 'intermediate', '["plyometrics","cardio"]', 30, 1),
(NULL, NULL, 'Burpees', 'Burpees', 'full_body', '[]', 'intermediate', '["cardio","conditioning"]', 30, 1),
(NULL, NULL, 'Double Unders', 'Doble Comba', 'cardio', '["jump rope"]', 'advanced', '["cardio","coordination"]', 40, 1),
(NULL, NULL, 'Jump Rope', 'Soga', 'cardio', '["jump rope"]', 'beginner', '["cardio"]', 60, 1),
(NULL, NULL, 'Mountain Climbers', 'Escaladores', 'core', '[]', 'beginner', '["cardio","core"]', 30, 1),
(NULL, NULL, 'Rowing', 'Remo Ergómetro', 'full_body', '["rower"]', 'all', '["cardio","back"]', 60, 1),
(NULL, NULL, 'Assault Bike', 'Bicicleta Assault', 'full_body', '["assault bike"]', 'all', '["cardio"]', 60, 1),
(NULL, NULL, 'Run 400m', 'Carrera 400m', 'cardio', '[]', 'all', '["cardio","endurance"]', 120, 1),
(NULL, NULL, 'Shuttle Run', 'Carrera de Ida y Vuelta', 'cardio', '[]', 'beginner', '["cardio","agility"]', 30, 1),
-- Legs
(NULL, NULL, 'Air Squats', 'Sentadillas', 'legs', '[]', 'beginner', '["legs","glutes"]', 30, 1),
(NULL, NULL, 'Back Squat', 'Sentadilla con Barra', 'legs', '["barbell","rack"]', 'intermediate', '["legs","glutes","strength"]', 40, 1),
(NULL, NULL, 'Front Squat', 'Sentadilla Frontal', 'legs', '["barbell","rack"]', 'advanced', '["legs","core","strength"]', 40, 1),
(NULL, NULL, 'Wall Ball', 'Lanzamiento a la Pared', 'legs', '["wall ball"]', 'intermediate', '["legs","shoulders","cardio"]', 30, 1),
(NULL, NULL, 'Lunges', 'Estocadas', 'legs', '[]', 'beginner', '["legs","glutes","balance"]', 30, 1),
(NULL, NULL, 'Bulgarian Split Squat', 'Sentadilla Búlgara', 'legs', '["bench"]', 'intermediate', '["legs","glutes","balance"]', 35, 1),
(NULL, NULL, 'Box Step-Ups', 'Subidas al Cajón', 'legs', '["box"]', 'beginner', '["legs","glutes"]', 30, 1),
(NULL, NULL, 'Romanian Deadlift', 'Peso Muerto Rumano', 'legs', '["barbell"]', 'intermediate', '["legs","glutes","back"]', 40, 1),
-- Back
(NULL, NULL, 'Deadlift', 'Peso Muerto', 'back', '["barbell"]', 'intermediate', '["back","legs","strength"]', 45, 1),
(NULL, NULL, 'Pull-ups', 'Dominadas', 'back', '["pull-up bar"]', 'advanced', '["back","arms"]', 30, 1),
(NULL, NULL, 'Chest-to-Bar Pull-ups', 'Dominadas Pecho a Barra', 'back', '["pull-up bar"]', 'advanced', '["back","arms"]', 30, 1),
(NULL, NULL, 'Ring Rows', 'Remo en Anillas', 'back', '["rings"]', 'beginner', '["back","arms"]', 30, 1),
(NULL, NULL, 'Bent Over Row', 'Remo Inclinado', 'back', '["barbell"]', 'intermediate', '["back","arms"]', 40, 1),
(NULL, NULL, 'Lat Pulldown', 'Jalón al Pecho', 'back', '["cable machine"]', 'beginner', '["back"]', 40, 1),
-- Chest
(NULL, NULL, 'Push-ups', 'Flexiones', 'chest', '[]', 'beginner', '["chest","arms","core"]', 30, 1),
(NULL, NULL, 'Ring Push-ups', 'Flexiones en Anillas', 'chest', '["rings"]', 'intermediate', '["chest","core","stability"]', 30, 1),
(NULL, NULL, 'Bench Press', 'Press de Banca', 'chest', '["barbell","bench","rack"]', 'intermediate', '["chest","arms"]', 45, 1),
(NULL, NULL, 'Dips', 'Fondos', 'chest', '["parallel bars"]', 'intermediate', '["chest","arms"]', 30, 1),
-- Shoulders
(NULL, NULL, 'Strict Press', 'Press Militar Estricto', 'shoulders', '["barbell"]', 'intermediate', '["shoulders","arms"]', 40, 1),
(NULL, NULL, 'Push Press', 'Push Press', 'shoulders', '["barbell"]', 'intermediate', '["shoulders","legs","power"]', 35, 1),
(NULL, NULL, 'Handstand Push-ups', 'Flexiones en Pino', 'shoulders', '["wall"]', 'advanced', '["shoulders","core"]', 30, 1),
(NULL, NULL, 'Lateral Raises', 'Elevaciones Laterales', 'shoulders', '["dumbbells"]', 'beginner', '["shoulders"]', 35, 1),
(NULL, NULL, 'Jerk', 'Split Jerk', 'shoulders', '["barbell"]', 'advanced', '["shoulders","legs","olympic"]', 40, 1),
-- Arms
(NULL, NULL, 'Ring Dips', 'Fondos en Anillas', 'chest', '["rings"]', 'advanced', '["chest","arms","stability"]', 30, 1),
(NULL, NULL, 'Bicep Curls', 'Curl de Bíceps', 'arms', '["dumbbells"]', 'beginner', '["arms"]', 35, 1),
(NULL, NULL, 'Tricep Extensions', 'Extensiones de Tríceps', 'arms', '["dumbbells"]', 'beginner', '["arms"]', 35, 1),
-- Core
(NULL, NULL, 'Sit-ups', 'Abdominales', 'core', '[]', 'beginner', '["core"]', 30, 1),
(NULL, NULL, 'GHD Sit-ups', 'Abdominales en GHD', 'core', '["ghd"]', 'intermediate', '["core"]', 30, 1),
(NULL, NULL, 'Toes-to-Bar', 'Pies a la Barra', 'core', '["pull-up bar"]', 'advanced', '["core","grip"]', 30, 1),
(NULL, NULL, 'Plank', 'Plancha', 'core', '[]', 'beginner', '["core","stability"]', 60, 1),
(NULL, NULL, 'L-Sit', 'L-Sit', 'core', '["parallel bars"]', 'advanced', '["core","arms"]', 20, 1),
(NULL, NULL, 'Russian Twists', 'Rotaciones Rusas', 'core', '[]', 'beginner', '["core"]', 30, 1),
-- Olympic
(NULL, NULL, 'Clean & Jerk', 'Cargada y Envión', 'full_body', '["barbell"]', 'advanced', '["olympic","power","full_body"]', 45, 1),
(NULL, NULL, 'Snatch', 'Arranque', 'full_body', '["barbell"]', 'advanced', '["olympic","power","full_body"]', 45, 1),
(NULL, NULL, 'Power Clean', 'Cargada de Potencia', 'full_body', '["barbell"]', 'intermediate', '["olympic","power"]', 40, 1),
(NULL, NULL, 'Kettlebell Swing', 'Balanceo con Pesa Rusa', 'full_body', '["kettlebell"]', 'beginner', '["full_body","cardio","power","hinge"]', 30, 1),
(NULL, NULL, 'Thruster', 'Thruster', 'full_body', '["barbell"]', 'intermediate', '["full_body","legs","shoulders"]', 35, 1),
-- Additional functional training essentials
(NULL, NULL, 'Hip Thrust', 'Empuje de Cadera', 'glutes', '["barbell","bench"]', 'beginner', '["glutes","hinge","strength"]', 40, 1),
(NULL, NULL, 'Farmer Carry', 'Cargada del Granjero', 'full_body', '["kettlebell"]', 'intermediate', '["full_body","grip","carry","core"]', 30, 1),
(NULL, NULL, 'Hang Power Clean', 'Cargada de Potencia desde Colgado', 'full_body', '["barbell"]', 'intermediate', '["olympic","power","pull"]', 40, 1),
(NULL, NULL, 'Push Jerk', 'Push Jerk', 'shoulders', '["barbell"]', 'advanced', '["shoulders","legs","olympic","power"]', 35, 1);

-- ============================================================
-- SAMPLE TEMPLATES
-- ============================================================

INSERT INTO templates (gym_id, created_by, name, description, blocks_json, total_duration, class_level) VALUES
(1, 3, 'Cindy', 'AMRAP clásico 20 minutos', 
 '[{"type":"briefing","name":"Briefing Cindy","config":{"duration":120,"title":"CINDY","description":"20 minutos AMRAP: 5 Pull-ups, 10 Push-ups, 15 Air Squats"},"exercises":[]},{"type":"amrap","name":"Cindy AMRAP","config":{"duration":1200},"exercises":[{"id":19,"name":"Pull-ups","reps":5},{"id":24,"name":"Push-ups","reps":10},{"id":10,"name":"Air Squats","reps":15}]}]',
 1320, 'intermediate'),
(1, 3, 'Murph', 'Hero WOD clásico',
 '[{"type":"briefing","name":"Briefing Murph","config":{"duration":180,"title":"MURPH","description":"1 Mile Run, 100 Pull-ups, 200 Push-ups, 300 Air Squats, 1 Mile Run"},"exercises":[]},{"type":"fortime","name":"Murph For Time","config":{"rounds":1,"time_cap":3600},"exercises":[{"id":8,"name":"Run 400m","reps":4},{"id":19,"name":"Pull-ups","reps":100},{"id":24,"name":"Push-ups","reps":200},{"id":10,"name":"Air Squats","reps":300},{"id":8,"name":"Run 400m","reps":4}]}]',
 3780, 'advanced'),
(1, 3, 'Tabata Core',
 'Tabata clásico enfocado en core', 
 '[{"type":"briefing","name":"Briefing","config":{"duration":60,"title":"TABATA CORE","description":"8 rondas de 20s on / 10s off por ejercicio"},"exercises":[]},{"type":"tabata","name":"Tabata Plank","config":{"rounds":8,"work":20,"rest":10},"exercises":[{"id":38,"name":"Plank"}]},{"type":"rest","name":"Descanso","config":{"duration":60},"exercises":[]},{"type":"tabata","name":"Tabata Sit-ups","config":{"rounds":8,"work":20,"rest":10},"exercises":[{"id":36,"name":"Sit-ups"}]},{"type":"rest","name":"Descanso","config":{"duration":60},"exercises":[]},{"type":"tabata","name":"Tabata T2B","config":{"rounds":8,"work":20,"rest":10},"exercises":[{"id":38,"name":"Toes-to-Bar"}]}]',
 840, 'intermediate');
