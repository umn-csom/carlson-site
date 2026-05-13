# CSM-219 SQL profiling summary (2026-05-11-182838)

Method: MariaDB file slow log was temporarily set to `long_query_time=0`, one cache-busted request was captured per page, then the original slow-query threshold was restored. XHProf was disabled during SQL capture and re-enabled afterward.

## Request Query Totals

| SQL total s | Queries | Rows examined | HTTP total s | HTTP | Label | Path |
|---:|---:|---:|---:|---:|---|---|
| 0.750311 | 3605 | 35290 | 2.973780 | 200 | degree finder view | `/graduate/resources/find-degree` |
| 0.730939 | 4432 | 35360 | 3.057576 | 200 | homepage | `/` |
| 0.702153 | 3715 | 34758 | 2.785613 | 200 | executive ed program node | `/executive-education/courses/emerging-leaders-bootcamp` |
| 0.698420 | 4266 | 37372 | 2.880905 | 200 | requested full-time mba | `/graduate/mba/full-time` |
| 0.665753 | 3604 | 34714 | 2.665831 | 200 | alumni events view | `/alumni/events` |
| 0.665450 | 3804 | 31229 | 2.636002 | 200 | faculty directory view | `/faculty-research/directory-tenured-tenure-track` |
| 0.617269 | 3519 | 32899 | 2.534026 | 200 | about page | `/about` |
| 0.586811 | 3531 | 34502 | 2.343967 | 200 | executive education landing | `/executive-education` |
| 0.583280 | 3390 | 32733 | 2.278124 | 200 | give page | `/give` |
| 0.507546 | 3668 | 35357 | 2.192955 | 200 | requested executive education courses | `/executive-education/courses` |
| 0.124175 | 513 | 5675 | 0.936833 | 200 | graduate landing page | `/graduate` |
| 0.068087 | 380 | 3238 | 0.442763 | 200 | news hub view | `/news` |

## Top Query Families Per Page

### graduate landing page (`/graduate`)
Request total `0.936833s`; SQL total `0.124175s`; queries `513`; rows examined `5675`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.013999 | 43 | 3 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.012512 | 70 | 70 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_discovery" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.011071 | 113 | 113 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.008909 | 12 | 2172 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.006247 | 1 | 0 | `INSERT INTO "cache_page" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.005571 | 3 | 543 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?')) AND ("id" NOT IN ('?', '?'));` |

### news hub view (`/news`)
Request total `0.442763s`; SQL total `0.068087s`; queries `380`; rows examined `3238`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.010366 | 44 | 2 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.008466 | 83 | 83 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_discovery" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.004930 | 1 | 2 | `SELECT "node_field_data"."created" AS "node_field_data_created", "node_field_data"."nid" AS "nid" FROM "node_field_data" "node_field_data" LEFT OUTER JOIN "group_relationship_field_data" "gcfd" ON node_field_data.nid=gcfd.entity_id AND gcfd.plugin_id IN ('?...` |
| 0.004082 | 6 | 1086 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.004009 | 38 | 38 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.003066 | 46 | 46 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |

### executive education landing (`/executive-education`)
Request total `2.343967s`; SQL total `0.586811s`; queries `3531`; rows examined `34502`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.186639 | 838 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.069184 | 608 | 1218 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.060647 | 84 | 15204 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.054727 | 1019 | 1003 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.034585 | 11 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.011999 | 209 | 190 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |

### alumni events view (`/alumni/events`)
Request total `2.665831s`; SQL total `0.665753s`; queries `3604`; rows examined `34714`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.176551 | 839 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.103455 | 613 | 1220 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.060508 | 84 | 15204 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.043188 | 1035 | 1018 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.033452 | 11 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.021181 | 217 | 209 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |

### faculty directory view (`/faculty-research/directory-tenured-tenure-track`)
Request total `2.636002s`; SQL total `0.665450s`; queries `3804`; rows examined `31229`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.176774 | 838 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.149530 | 625 | 1242 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.047827 | 68 | 12308 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.042003 | 1047 | 1008 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.030344 | 11 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.013622 | 244 | 241 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |

### executive ed program node (`/executive-education/courses/emerging-leaders-bootcamp`)
Request total `2.785613s`; SQL total `0.702153s`; queries `3715`; rows examined `34758`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.217242 | 838 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.099323 | 618 | 1232 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.063181 | 84 | 15204 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.060571 | 1038 | 1008 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.031938 | 11 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.020632 | 301 | 291 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |

### about page (`/about`)
Request total `2.534026s`; SQL total `0.617269s`; queries `3519`; rows examined `32899`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.192608 | 838 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.120485 | 608 | 1218 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.052420 | 69 | 12489 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.051190 | 1067 | 1048 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.033184 | 12 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.014936 | 239 | 231 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |

### give page (`/give`)
Request total `2.278124s`; SQL total `0.583280s`; queries `3390`; rows examined `32733`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.187758 | 838 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.067148 | 610 | 1218 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.057253 | 69 | 12489 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.048932 | 1037 | 1023 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.037406 | 13 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.023109 | 218 | 214 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |

### degree finder view (`/graduate/resources/find-degree`)
Request total `2.973780s`; SQL total `0.750311s`; queries `3605`; rows examined `35290`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.202528 | 840 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.117156 | 613 | 1228 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.077701 | 84 | 15204 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.058213 | 1021 | 997 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.047695 | 11 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.016224 | 1 | 886 | `SELECT "menu_tree".* FROM "menu_tree" "menu_tree" WHERE "menu_name" = '?' ORDER BY "p1" ASC, "p2" ASC, "p3" ASC, "p4" ASC, "p5" ASC, "p6" ASC, "p7" ASC, "p8" ASC, "p9" ASC;` |

### homepage (`/`)
Request total `3.057576s`; SQL total `0.730939s`; queries `4432`; rows examined `35360`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.213774 | 850 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.071309 | 1155 | 1119 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.052409 | 68 | 12308 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.049852 | 621 | 1232 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.033666 | 10 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.029776 | 439 | 432 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |

### requested full-time mba (`/graduate/mba/full-time`)
Request total `2.880905s`; SQL total `0.698420s`; queries `4266`; rows examined `37372`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.214320 | 840 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.106987 | 622 | 1232 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.070074 | 105 | 19005 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.060115 | 1070 | 1010 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.031464 | 11 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.025211 | 461 | 451 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_config" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |

### requested executive education courses (`/executive-education/courses`)
Request total `2.192955s`; SQL total `0.507546s`; queries `3668`; rows examined `35357`.

| SQL total s | Count | Rows examined | Query family |
|---:|---:|---:|---|
| 0.164842 | 840 | 89 | `SELECT DISTINCT "d"."plugin_id" AS "plugin_id" FROM "group_relationship_field_data" "d" WHERE ("entity_id" = '?') AND ("plugin_id" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'));` |
| 0.061570 | 621 | 1240 | `SELECT "base_table"."path" AS "path", "base_table"."alias" AS "alias" FROM "path_alias" "base_table" WHERE ("base_table"."status" = '?') AND ("base_table"."path" LIKE '?' ESCAPE '?') AND ("base_table"."langcode" IN ('?', '?')) ORDER BY "base_table"."langcod...` |
| 0.054458 | 84 | 15204 | `SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = '?') AND ("expanded" = '?') AND ("has_children" = '?') AND ("enabled" = '?') AND ("parent" IN ('?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', ...` |
| 0.035605 | 1047 | 1015 | `SELECT "cid", "data", "created", "expire", "serialized", "tags", "checksum" FROM "cache_entity" WHERE "cid" IN ( '?' ) ORDER BY "cid";` |
| 0.028415 | 11 | 0 | `INSERT INTO "cache_menu" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = VA...` |
| 0.011188 | 41 | 0 | `INSERT INTO "cache_render" ("cid", "expire", "created", "tags", "checksum", "data", "serialized") VALUES ('?', '?', '?', '?', '?', '?', '?') ON DUPLICATE KEY UPDATE "cid" = VALUES("cid"), "expire" = VALUES("expire"), "created" = VALUES("created"), "tags" = ...` |

Raw CSV: `csm-219-sql-profile-results-2026-05-11-182838.csv`
