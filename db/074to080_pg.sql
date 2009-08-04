BEGIN;
create sequence design_seq;
create table design (
    id bigint default nextval('design_seq') /* comment 'design ID'*/,
    backgroundcolor integer /* comment 'main background color'*/ ,
    contentcolor integer /*comment 'content area background color'*/ ,
    sidebarcolor integer /*comment 'sidebar background color'*/ ,
    textcolor integer /*comment 'text color'*/ ,
    linkcolor integer /*comment 'link color'*/,
    backgroundimage varchar(255) /*comment 'background image, if any'*/,
    disposition int default 1 /*comment 'bit 1 = hide background image, bit 2 = display background image, bit 4 = tile background image'*/,
    primary key (id)
);
alter table "user"
     add column design_id integer references design(id);
alter table "user"
     add column viewdesigns integer default 1;

alter table notice add column
     conversation integer references notice (id);

create index notice_conversation_idx on notice(conversation);

alter table foreign_user
     alter column id TYPE bigint;
     
alter table foreign_user alter column id set not null;

alter table foreign_link
     alter column foreign_id TYPE bigint;

alter table user_group
      add column design_id integer;

/*attachments and URLs stuff */
create sequence file_seq;
create table file (
    id bigint default nextval('file_seq') primary key /* comment 'unique identifier' */,
    url varchar(255) unique, 
    mimetype varchar(50), 
    size integer, 
    title varchar(255), 
    date integer, 
    protected integer,
    filename text /* comment 'if a local file, name of the file' */,
    modified timestamp default CURRENT_TIMESTAMP /* comment 'date this record was modified'*/
);

create sequence file_oembed_seq;
create table file_oembed (
    file_id bigint default nextval('file_oembed_seq') primary key /* comment 'unique identifier' */,
    version varchar(20),
    type varchar(20),
    provider varchar(50),
    provider_url varchar(255),
    width integer,
    height integer,
    html text,
    title varchar(255),
    author_name varchar(50), 
    author_url varchar(255), 
    url varchar(255) 
);

create sequence file_redirection_seq;
create table file_redirection (
    url varchar(255) primary key, 
    file_id bigint, 
    redirections integer, 
    httpcode integer
);

create sequence file_thumbnail_seq;
create table file_thumbnail (
    file_id bigint primary key, 
    url varchar(255) unique, 
    width integer, 
    height integer 
);
create sequence file_to_post_seq;
create table file_to_post (
    file_id bigint, 
    post_id bigint, 

    primary key (file_id, post_id)
);


create table group_block (
   group_id integer not null /* comment 'group profile is blocked from' */ references user_group (id),
   blocked integer not null /* comment 'profile that is blocked' */references profile (id),
   blocker integer not null /* comment 'user making the block'*/ references "user" (id),
   modified timestamp /* comment 'date of blocking'*/ ,

   primary key (group_id, blocked)
);

create table group_alias (

   alias varchar(64) /* comment 'additional nickname for the group'*/ ,
   group_id integer not null /* comment 'group profile is blocked from'*/ references user_group (id),
   modified timestamp /* comment 'date alias was created'*/,
   primary key (alias)

);
create index group_alias_group_id_idx on group_alias (group_id);

COMMIT;