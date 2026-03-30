\connect feathertrend_test

INSERT INTO species (id, scientific_name, common_name) VALUES (1, 'Alcedo atthis', 'Common Kingfisher');
INSERT INTO pictures (species_id, datetime, path) VALUES (1, '2016-01-01T12:00:00', 'kingfisher.jpg');
INSERT INTO pictures (species_id, datetime, path) VALUES (1, '2019-01-05T12:00:00', 'kingfisher.jpg');
INSERT INTO pictures (species_id, datetime, path) VALUES (1, '2020-01-15T12:00:00', 'kingfisher.jpg');

INSERT INTO species (id, scientific_name, common_name) VALUES (2, 'Gypaetus barbatus', 'Bearded Vulture');
INSERT INTO pictures (species_id, datetime, path) VALUES (2, '2016-04-01T12:00:00', 'gyp.jpg');
INSERT INTO pictures (species_id, datetime, path) VALUES (2, '2015-05-01T12:00:00', 'gyp.jpg');

INSERT INTO users (email, roles, password) VALUES ('test@example.com', '[]', 'THIS_IS_A_PASSWORD_HASH');
