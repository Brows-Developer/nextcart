UPDATE `modules` SET `modules_label` = 'System' WHERE `modules`.`modules_id` = 5;

UPDATE `sub_modules` SET `sub_modules_id` = '4' WHERE `sub_modules`.`sub_modules_id` = 3;

UPDATE `sub_modules` SET `sub_modules_id` = '5' WHERE `sub_modules`.`sub_modules_id` = '4';

INSERT INTO `sub_modules` (`sub_modules_id`, `sub_modules_label`, `sub_modules_description`, `sub_modules_url`, `sub_modules_urls`, `modules_id`) VALUES ('3', 'Settings', NULL, '#/settings', '/settings', '5')