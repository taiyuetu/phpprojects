<!-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved. -->

# 叁程 CRM (Triphase CRM) 更新日志 (Changelog)

本项目遵循 [Semantic Versioning (语义化版本)](https://semver.org/lang/zh-CN/) 规范。
版本格式：`主版本号.次版本号.修订号` (Major.Minor.Patch)

---

## [v1.3.0] - 2026-09-04
### 产品身份 (Identity)
- **系统定名「叁程 CRM / Triphase CRM」**：取“线索 → 商机 → 客户”三段行程之意（仓库名 `先leads再商机再客户` 的产品化表达）。新增常量 `APP_NAME_EN` / `APP_TAGLINE` / `APP_AUTHOR` / `APP_COPYRIGHT` / `APP_COPYRIGHT_UI` / `APP_RIGHTS`；默认系统名称与副标题随之下调（`config.php` + `Setting::defaults()`）。`SESSION_NAME` 改为 `sancheng_crm_session`。系统名称仍是“设置 → 应用信息”里的可编辑项，`APP_NAME` 只作为默认值。
- **版权信息**：全部源文件（PHP / SQL / CSS / .htaccess / .env.example / Markdown）头部添加统一声明  “Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.”；页面输出侧（侧边栏底部、登录页脚、`<meta name="author">`、`<meta name="copyright">`）同步展示，并提供可编辑的 `copyright_notice` 应用设置（部署给自家客户时可改成贵司主体）。

### 新增功能 (Added)
- **设置模块**（`/settings`，侧边栏新增“设置”入口与账户下拉直达项）：
  - **应用信息**（仅管理员）：系统名称 / 系统副标题 / 公司名称 / 货币符号。存入新的 `app_settings` 键值表，由 `appSetting()` / `appName()` / `money()` 读取，保存后全站即时生效（侧边栏、浏览器标题、登录页、所有金额），并可逐项或一次性恢复默认。
  - **个人信息**：姓名、邮箱（同时用于登录）、职位、电话、WhatsApp、备注；另含**修改密码**（校验当前密码）。
- **负责人信息单点维护、跨模型同步**：客户 / 线索 / 商机 / 订单只存 `owner_id`，跟进与动态只存 `user_id`，附件只存 `uploaded_by`，姓名一律在读取时 JOIN `users`。因此在设置里修改个人信息，会**立即同步**到客户“负责人”等所有引用处（客户详情页还会连带展示负责人的职位 / 电话 / WhatsApp / 邮箱）。设置页新增“信息同步范围”面板，实时列出当前账号被引用的记数。
- **新增代码**：模型 `app/models/Setting.php`、控制器 `app/controllers/SettingController.php`、视图 `app/views/settings/index.php`；视图助手 `ownerLabel()` / `ownerBlock()`；迁移 `database/migrations/005_add_profile_fields_to_users.sql`。

### 数据库 (Schema)
- `users` 新增 `phone` / `whatsapp` / `job_title` / `notes` / `updated_at`；新增 `app_settings` 表与默认值种子。升级：`php database/migrate.php`（旧库正常 ALTER 加列，全新库由基线提供、增量自动 `skipped`）。
- `migrate.php` 自检表数量 9 → 10（含 `app_settings`）。

### 修复与优化 (Fixed & Improved)
- `currentUser()` 改为从 `users` 解析（`User::identity()` 每请求缓存一次），不再信任登录时的 Session 快照；保存个人信息后 `User::syncSession()` 刷新缓存，顶栏与表单立即显示新值。
- `Controller::requireRole()` 同步走 `currentUser()`，角色变更不再需要重新登录才生效。
- 新增 `textLength()` / `textTrim()` 多字节安全计数（mbstring 缺失时回退 iconv / PCRE），避免将中文按字节计入长度限制。

### 测试 (Tests)
- 新增 `tests/cases/SettingTest.php`（11 项）：设置默认/读写/缺失表兜底、`sanitize()` 过滤未知键与非法枚举、货币符号驱动 `money()`、资料校验（空姓名 / 邮箱重复 / 失败不回写）、改名后客户・线索・订单负责人同步、引用计数、页面渲染与权限可见性、**“禁止将用户姓名冗余存入业务表”结构不变量**。
- `HttpSmokeTest` 拆为两个用例：全页面 200（含 `/settings` 三个 tab）与线索列契约；新增端到端设置用例 —— 应用信息写入后顶栏/金额变化、个人资料改名后客户列表与详情页同步、非管理员提交应用信息被拒。
- 本地全量：10 cases / 42 assertions 全部通过。

---

## [v1.2.1] - 2026-09-04
### 界面与交互 (UI)
- **线索列表列优化**：`流失原因` 列仅在“已流失”标签页下展示；全部 / 新建 / 已联系 / 已确认不再占用该列（无数据时的提示行 `colspan` 随之自适应）。

### 修复与优化 (Fixed & Improved)
- **迁移工具幂等性**：`database/migrate.php` 对“通篇仅 `ALTER TABLE ... ADD COLUMN`”的增量文件先比对实际表结构，列已存在（因 `schema.sql` 基线已包含）时只登记不执行，输出 `skipped: NNN_xxx.sql`。修复了 v1.2.0 将 `wechat` / `shipping_address` 写入基线后，空库执行 `migrate.php` 直报 `duplicate column name: wechat` 、导致全部测试用例无法建库的问题；旧数据库缺列时仍会正常执行 ALTER。
- **测试零 curl 依赖**：`tests/bootstrap.php` 新增 `TestHttp`（基于 PHP 流实现表单 POST / Cookie / 3xx 跟随），`HttpSmokeTest`、`DealStageFlowTest` 不再依赖 curl 扩展（仅需默认开启的 `allow_url_fopen`）。
- **测试覆盖增强**：新增 `tests/cases/MigrationTest.php`（空库构建、幂等重跑、跳过登记、旧库升级、基线列一致性）；`HttpSmokeTest` 补齐四个线索标签页的 200 检查与“流失原因”列展示契约断言。全量用例：9 cases / 30 assertions 全部通过。

---

## [v1.2.0] - 2026-09-03
### 架构与工程 (Architecture & Tooling)
- **SQLite 迁移**：数据库从 MySQL 迁移至 SQLite，全库仅一个文件，无需额外服务。
- **统一迁移体系**：新增 `database/migrate.php` 一键建库/升级/修复（幂等自愈）；`schema.sql` 为唯一权威基线；`database/migrations/` 存放一次性 ALTER 变更；迁移执行历史记录于 `_migrations` 表。
- **类自动加载器**：新增 `app/core/autoloader.php`，视图可直接调用模型静态助手，不再依赖控制器手动预加载类。
- **零依赖测试体系**：新增 `tests/`（PHP CLI，无 Composer），覆盖 Model CRUD、状态流转、归档生命周期、商品同步、附件 copyTo 回归、视图无预加载渲染、HTTP 冒烟等 8 个用例。

### 新增功能 (Added)
- **订单管理模块**：订单列表/详情/新建/编辑/删除、商品明细行（数量/单价/小计自动汇总）、订单状态与付款状态、订单编号自动生成、分页筛选。
- **订单附件**：商机与订单均支持上传附件（图片/PDF/Excel/CSV/压缩包，≤20MB），商机成交时附件自动继承到订单。
- **商机自动生成订单**：商机阶段改为"成交"时自动创建订单（金额=商品明细合计），可先行填写商品明细。

### 商机流转规则 (Deal lifecycle)
- **成交不归档**：成交(closed_won)自动转订单后商机保留在看板"成交"列，便于查阅。
- **丢单归档**：丢单(closed_lost)商机自动归档并移出看板；看板仅保留 进行中/方案阶段/谈判中/成交 四列。
- **恢复回进行中**：已归档页点"恢复"，商机回到"进行中"(open)列并可重新跟进。

### 修复与优化 (Fixed & Improved)
- 商机成交转订单时附件未同步的问题。
- 附件上传后无法显示/继承的历史问题。
- 视图层因缺少预加载导致的 "Class not found" 运行时错误。
- 商机成交后未归档/重复归档等规则问题。

---

## [v1.1.1] - 2026-08-26
### 修复与优化 (Fixed & Improved)
- **数据一致性保护**：在线索转化为商机流程（`LeadController::convert`）中引入数据库事务 (`beginTransaction` / `commit` / `rollBack`)，确保“客户创建 + 商机创建 + 线索更新”原子性执行。
- **删除级联防误删**：修复删除已转化线索时级联误删正式客户及全部历史商机的严重隐患，现删除线索仅删除该线索本身。
- **登录防暴力破解**：登录接口新增 5 次失败重试限制与 60 秒冷却时间锁，提升账户安全性。
- **会话与权限机制**：优化 Session 角色读取机制，支持团队成员协同跟进与维护商机、客户及线索。
- **版本管理规范**：建立统一的 `CHANGELOG.md` 与全局 `APP_VERSION` 版本常量。

---

## [v1.1.0] - 2026-08-20
### 新增特性 (Added)
- **客户 360° 详情页**：新增客户详情聚合面板，整合商机列表、来源线索、比价与跟进记录、活动时间轴。
- **外贸业务属性扩展**：客户和线索模型增加 `whatsapp`、`facebook`、`tiktok`、`website`、`source_country`、`source_city`、`first_purchase_from_china`、`has_import_capability` 等跨境字段。
- **线索流失与重新激活**：
  - 支持记录线索流失原因（`no_need`、`competitor`、`budget`、`no_match`、`no_response` 等）。
  - 支持对已流失线索进行一键重新激活。
- **商机阶段时间轴追踪**：商机流转到各个阶段（进行中、方案阶段、谈判中、成交、丢单）时自动记录精确时间戳。

---

## [v1.0.0] - 2026-08-01
### 初始版本发布 (Initial Release)
- **核心架构**：基于 PHP 8 原生轻量级 MVC 框架开发，无第三方重量级依赖。
- **模块支持**：
  - 用户认证系统（登录、注册、CSRF 防护、密码 BCrypt 哈希加密）。
  - 仪表盘（统计核心商机金额、成交金额、活跃客户数及近期动态）。
  - 客户管理（基础增删改查、分页与模糊搜索）。
  - 线索管理（线索新建、编辑、转商机）。
  - 商机看板（按阶段分类展示与管理）。
