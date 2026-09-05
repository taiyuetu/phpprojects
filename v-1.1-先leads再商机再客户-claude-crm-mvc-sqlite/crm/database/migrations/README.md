<!-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved. -->

# database/migrations/

目录用于存放**对现有表的结构性增量变更**（例如 `ALTER TABLE ... ADD COLUMN`），
这些变更无法用幂等的 `CREATE ... IF NOT EXISTS` 表达。

## 规则

1. **新增整张表 / 索引 / 触发器** → 不要放在这里。
   直接写进 `database/schema.sql`（canonical 文件），
   然后运行 `php database/migrate.php` 即可 —— 脚本每次都会幂等重跑 schema.sql，
   新表会自动补到任何旧数据库上。

2. **修改已有表结构（加列等，需 ALTER）** → 在这里新建一个文件：
   ```
   database/migrations/001_add_something.sql
   ```
   文件命名必须是数字开头的序号，脚本按名称排序、**只执行一次**，
   已执行过的文件名会记录在数据库的 `_migrations` 表中。

3. 执行入口统一为：
   ```bash
   php database/migrate.php          # 应用 schema.sql + 所有未执行过的 migrations/*.sql
   php database/migrate.php --status # 查看已执行/待执行
   ```

## 注意

- 旧的一次性脚本（`migrate_add_*.sql`、`migrate_attachments.php`）已被合并进
  schema.sql 后删除。schema.sql 才是唯一事实来源。
- **基线已包含的加列无需手工处理**：`migrate.php` 会先比对实际表结构，
  若某个增量文件通篇只有 `ALTER TABLE ... ADD COLUMN` 且这些列在库里已存在
  （全新数据库由 schema.sql 一次性建全），则只登记、不执行，输出
  `skipped: NNN_xxx.sql (column(s) already present in baseline)`；
  旧数据库缺列时仍会正常执行。因此“同步修改 schema.sql”不会造成
  `duplicate column name` 报错。
- 含建表/重建表/改 CHECK 等其它语句的增量文件**不会**被自动跳过，会原样执行。

## 需要回填或要 UNIQUE 的列：拆成两个增量，并且种子不要引用它

给既有表加一个「历史行要回填、而且必须唯一」的列时（例：`006`/`007` 的 `public_code`），
单靠一个文件做不到，因为：

1. SQLite 的 `ALTER TABLE ... ADD COLUMN` **不能带 `UNIQUE`**，唯一性只能事后建索引；
2. 而 `migrate.php` 每次都会**重放基线**（自愈），所以基线里的种子数据
   （`INSERT OR IGNORE INTO …`）**绝对不能引用只有增量才带来的列**：
   旧库上那一列还不存在，重放基线会直接
   `table customers has no column named public_code` 整体失败（本次开发真的撞到过）。

所以分成两步：

| 文件 | 内容 | 新库 | 老库 |
| --- | --- | --- | --- |
| `006_add_public_code_to_core_tables.sql` | 通篇只有 `ADD COLUMN`（不带 UNIQUE） | 自动 `skipped` | 正常执行，补列 |
| `007_backfill_public_code.sql` | 先 `UPDATE … WHERE public_code IS NULL` 回填，再 `CREATE UNIQUE INDEX IF NOT EXISTS` | 执行（把基线留下的 NULL 补上） | 执行（把刚补的 NULL 补上） |

基线里该列只声明为普通 `TEXT`，列的存在形式与老库补列后的结果一致；
历史行即使在回填前也不能在界面上留白，靠 `Model::codeOf()` 按同一规则推导兼容。

回归由 `tests/cases/PublicCodeTest.php::test_the_migrations_backfill_and_stay_idempotent` 守住：
它用“剥掉新列声明的基线”现场造一个伪老库，真跑 `migrate.php` 验补列→回填→唯一索引→重跑幂等。
- 增量文件最好写成可重复执行的安全形式（先判断列/表是否存在），
  防止脚本记录与实际执行不一致时出错。
