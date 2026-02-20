-- GymFlow: Spotify per-instructor migration
-- Run this on production after deploying the new code

ALTER TABLE instructor_profiles
    ADD COLUMN spotify_client_id     VARCHAR(100) DEFAULT NULL AFTER preferences_json,
    ADD COLUMN spotify_client_secret VARCHAR(100) DEFAULT NULL AFTER spotify_client_id;

-- Remove spotify credentials from gyms (they now live on instructor_profiles)
-- spotify_mode stays on gyms as a feature flag (enabled/disabled per gym)
ALTER TABLE gyms
    DROP COLUMN IF EXISTS spotify_client_id,
    DROP COLUMN IF EXISTS spotify_client_secret;
