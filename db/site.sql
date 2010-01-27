/* For managing multiple sites */

create table status_network (

    nickname varchar(64) primary key comment 'nickname',
    hostname varchar(255) unique key comment 'alternate hostname if any',
    pathname varchar(255) unique key comment 'alternate pathname if any',

    dbhost varchar(255) comment 'database host',
    dbuser varchar(255) comment 'database username',
    dbpass varchar(255) comment 'database password',
    dbname varchar(255) comment 'database name',

    sitename varchar(255) comment 'display name',
    theme varchar(255) comment 'theme name',
    logo varchar(255) comment 'site logo',
    
    tags text comment 'site meta-info tags (pipe-separated)',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
