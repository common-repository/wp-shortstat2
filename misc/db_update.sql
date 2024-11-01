UPDATE 'wp_ss_stats' SET 'language' = '' WHERE 'language' = 'empty'
UPDATE 'wp_ss_stats' SET 'browser' = '' WHERE 'browser' = 'Indeterminable'
UPDATE 'wp_ss_stats' SET 'version' = '' WHERE 'version' = 'Indeterminable'
UPDATE 'wp_ss_stats' SET 'platform' = '' WHERE 'platform' = 'Indeterminable'
ALTER TABLE 'wp_ss_stats' CHANGE 'country' 'country' CHAR( 2 ) NOT NULL
ALTER TABLE 'wp_ss_stats' DROP 'user_agent'
