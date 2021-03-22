/*
 script to prepare database for test execution
 */
insert into users (v2_id, name, token,password) values ('909090909', 'testuser', 'token', 'password');
select id from users where v2_id='909090909' # returns 1255629. modify below to match 

insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey_none') ; 
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-101') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-102') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-103') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-111') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-113') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-115') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-121') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-123') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-125') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-131') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-133') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-135') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-141') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-143') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-145') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-153') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-155') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-163') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-165') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-173') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-175') ;
insert into dbp_users.user_keys (user_id, `key`) values (1255629, 'testkey-183') ;


insert into access_group_api_keys (access_group_id, key_id)
select substring(`key`,9), id 
from user_keys
where `key` like 'testkey-%'
order by id;