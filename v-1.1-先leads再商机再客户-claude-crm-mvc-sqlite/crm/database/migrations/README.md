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
- 增量文件最好写成可重复执行的安全形式（先判断列/表是否存在），
  防止脚本记录与实际执行不一致时出错。
