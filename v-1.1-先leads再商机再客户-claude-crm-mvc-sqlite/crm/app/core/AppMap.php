<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

/**
 * AppMap — a machine-readable map of this application, built by introspecting
 * the running code and database instead of being typed by hand.
 *
 * Why it exists: hand-written docs drift, and a drifting doc is worse than none
 * — especially when an AI reads it to decide what to do. Routes come from
 * app/routes.php, columns/constraints from SQLite, settings from
 * Setting::definitions(), AI tools from Ai::tools(). Only the flow rules
 * (self::flows()) are authored, because they encode intent rather than structure.
 *
 * Consumers:
 *   - 使用说明页 (app/views/help/index.php → _tech.php) renders it as HTML
 *   - GET /help/context renders it as plain text for pasting into an LLM
 *   - Ai::systemPrompt() injects the compact sections so DeepSeek/Qwen/etc.
 *     answer against the real schema and the real permission rules
 */
class AppMap
{
    /** Compact map injected into the AI prompt is capped, in characters. */
    public const COMPACT_LIMIT = 2600;

    private static ?array $cache = null;

    /** Drop the per-request cache (after a schema/settings change, or between tests). */
    public static function flushCache(): void
    {
        self::$cache = null;
    }

    /** The full map. One DB/router read per request. */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        return self::$cache = [
            'app'        => self::appInfo(),
            'php'        => self::phpInfo(),
            'routes'     => self::routes(),
            'schema'     => self::schema(),
            'settings'   => self::settings(),
            'ai_tools'   => self::aiTools(),
            'ai_models'  => self::aiProviders(),
            'flows'      => self::flows(),
            'conventions'=> self::conventions(),
            'tests'      => self::tests(),
        ];
    }

    // ------------------------------------------------------------- collectors

    public static function appInfo(): array
    {
        return [
            'name'      => appName(),
            'name_en'   => APP_NAME_EN,
            'version'   => APP_VERSION,
            'env'       => APP_ENV,
            'db'        => DB_PATH,
            'db_size'   => (int) (is_file(DB_PATH) ? filesize(DB_PATH) : 0),
            'copyright' => APP_COPYRIGHT . ' — ' . APP_RIGHTS,
        ];
    }

    public static function phpInfo(): array
    {
        $tz = date_default_timezone_get();
        $utc = (new PDO('sqlite::memory:'))->query("select datetime('now')")->fetchColumn();
        $skew = (int) round((time() - strtotime((string) $utc)) / 3600);

        return [
            'version'    => PHP_VERSION,
            'timezone'   => $tz,
            'utc_offset' => $skew,
            'sqlite'     => (new PDO('sqlite::memory:'))->query('select sqlite_version()')->fetchColumn(),
            'transports' => stream_get_transports(),
            'https'      => AiClient::httpsAvailable(),
            'extensions' => [
                'pdo_sqlite' => extension_loaded('pdo_sqlite'),
                'sqlite3'    => extension_loaded('sqlite3'),
                'openssl'    => extension_loaded('openssl'),
                'curl'       => extension_loaded('curl'),
                'mbstring'   => extension_loaded('mbstring'),
                'fileinfo'   => extension_loaded('fileinfo'),
                'gd'         => extension_loaded('gd'),
            ],
        ];
    }

    /** Route table taken from the real router (app/routes.php), grouped by method. */
    public static function routes(): array
    {
        $router = new Router();
        require APP_PATH . '/routes.php';
        $out = [];
        foreach ($router->all() as $method => $map) {
            foreach ($map as $path => $handler) {
                $out[] = ['method' => $method, 'path' => $path, 'handler' => $handler];
            }
        }
        return $out;
    }

    /**
     * Tables, columns, keys and CHECK enums straight from SQLite.
     *
     * @return array<string,array{columns:array<int,array<string,mixed>>,primary:string,pk_columns:array<int,string>,foreign:array<int,string>,checks:array<int,string>,indexes:array<int,string>,rows:int}>
     */
    public static function schema(): array
    {
        // 结构读取统一在 Schema（PRAGMA + sqlite_master）。它不依赖业务类，
        // 所以 Ai 生成字段参数时也能安全调用，不会绕回到这里（AppMap → Ai → AppMap 会无限递归）。
        return Schema::all();
    }

    /** @deprecated 结构详情请看 Schema::all()；以方法保留给旧调用点 */
    private static function schemaLegacy(): array
    {
        // The Database wrapper (not the raw PDO handle) provides query/bind/resultSet.
        $db = new Database();
        $names = $db->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->resultSet();

        $out = [];
        foreach ($names as $row) {
            $table = $row['name'];
            $cols = $db->query('SELECT name, type, "notnull", dflt_value, pk FROM pragma_table_info(:t) ORDER BY cid')
                ->bind(':t', $table)->resultSet();
            $row = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name = :t")
                ->bind(':t', $table)->single();
            $sql = (string) ($row['sql'] ?? '');

            $checks = [];
            // CHECK (status IN ('a','b')) nests parentheses: a plain [^)]* capture would
            // stop at the first ")" and the enum values would be lost.
            if (preg_match_all('/CHECK\s*\(((?:[^()]|\([^()]*\))*)\)/i', $sql, $m)) {
                foreach ($m[1] as $c) {
                    $checks[] = preg_replace('/\s+/', ' ', trim($c));
                }
            }
            $foreign = [];
            if (preg_match_all('/FOREIGN KEY\s*\((\w+)\)\s*REFERENCES\s*(\w+)\s*\((\w+)\)([^,]*)/i', $sql, $m, PREG_SET_ORDER)) {
                foreach ($m as $f) {
                    $foreign[] = $f[1] . ' → ' . $f[2] . '.' . $f[3] . (trim($f[4] ?? '') ? ' (' . trim($f[4]) . ')' : '');
                }
            }
            $indexes = [];
            foreach ($db->query("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name = :t AND sql IS NOT NULL")
                ->bind(':t', $table)->resultSet() as $ix) {
                $indexes[] = (string) $ix['name'];
            }
            try {
                $rows = (int) $db->query("SELECT COUNT(*) AS c FROM \"{$table}\"")->single()['c'];
            } catch (Throwable $e) {
                $rows = -1;
            }

            $out[$table] = [
                'columns'    => $cols,
                'primary'    => 'id',
                'pk_columns' => array_values(array_map(static fn($c) => (string) $c['name'],
                    array_filter($cols, static fn($c) => (int) $c['pk'] > 0))),
                'foreign'    => $foreign,
                'checks'     => $checks,
                'indexes'    => $indexes,
                'rows'       => $rows,
            ];
        }
        return $out;
    }

    /** Editable settings + which are secrets + the env override names. */
    public static function settings(): array
    {
        $values  = Setting::values();
        $secrets = Setting::secretState();
        $out = [];
        foreach (Setting::definitions() as $key => $def) {
            $isSecret = !empty($def['secret']);
            $out[] = [
                'key'     => $key,
                'label'   => $def['label'],
                'group'   => $def['group'] ?? 'app',
                'type'    => $def['type'] ?? 'text',
                'default' => $def['default'],
                'current' => $isSecret ? (($secrets[$key]['set'] ?? false) ? '已设置（' . ($secrets[$key]['masked'] ?? '') . '）' : '未设置') : (string) ($values[$key] ?? ''),
                'secret'  => $isSecret,
                'options' => $isSecret ? [] : array_column(Setting::definitionOptions($key), 'value'),
                'env'     => strtoupper($key),
            ];
        }
        return $out;
    }

    /** The AI whitelist, straight from the code that enforces it. */
    public static function aiTools(): array
    {
        $out = [];
        foreach (Ai::tools() as $name => $tool) {
            $params = [];
            foreach ($tool['params'] as $key => $spec) {
                $params[] = [
                    'name'     => $key,
                    'label'    => $spec['label'],
                    'type'     => $spec['type'],
                    'required' => !empty($spec['required']),
                    'options'  => $spec['options'] ?? [],
                ];
            }
            $kind = (string) ($tool['kind'] ?? 'write');
            $out[] = [
                'name'        => $name,
                'label'       => $tool['label'],
                'kind'        => $kind,
                'kind_label'  => ['read' => '查询', 'write' => '写入', 'delete' => '删除'][$kind] ?? $kind,
                'destructive' => $kind === 'delete',
                'hint'        => (string) ($tool['hint'] ?? ''),
                // the old boolean stays, derived — so anything still reading it is right
                'write'       => $kind !== 'read',
                'params'      => $params,
            ];
        }
        return $out;
    }

    public static function aiProviders(): array
    {
        $out = [];
        foreach (AiClient::providers() as $key => $p) {
            $out[] = [
                'key'     => $key,
                'label'   => $p['label'],
                'base'    => $p['base'],
                'default' => $p['default_model'],
                'models'  => $p['models'] ?? [],
                'needs_key' => (bool) $p['key_required'],
                // what 快速模式 actually sends for this provider (empty = no such switch)
                'fast'      => !empty($p['fast_params']) ? json_encode($p['fast_params'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            ];
        }
        return $out;
    }

    /** Test inventory, counted from the files themselves. */
    public static function tests(): array
    {
        $out = [];
        $dir = BASE_PATH . '/tests/cases';
        foreach (glob($dir . '/*Test.php') ?: [] as $file) {
            $src = (string) file_get_contents($file);
            $out[] = [
                'name'  => basename($file, '.php'),
                'tests' => preg_match_all('/^function test_/m', $src),
                'file'  => 'tests/cases/' . basename($file),
            ];
        }
        return $out;
    }

    // ------------------------------------------------ authored knowledge

    /**
     * The intent layer: state machines, side effects and permission rules.
     * These cannot be introspected, so they are written here — and _tech.php /
     * the AI prompt both read from here, so there is still only one copy.
     *
     * @return array<int,array{title:string,steps:array<int,string>,rules:array<int,string>}>
     */
    public static function flows(): array
    {
        return [
            [
                'title' => '请求生命周期（每个 HTTP 请求都走这条路）',
                'steps' => [
                    '浏览器 → public/.htaccess 把所有请求重写进 public/index.php（唯一入口，也是唯一暴露的目录）',
                    'public/index.php require app/bootstrap.php',
                    'bootstrap.php：定义 APP_PATH/BASE_PATH → date_default_timezone_set(Asia/Shanghai) → 注册 autoloader（app/core、app/models、app/controllers 三处按类名自动加载）',
                    'app/config/config.php：读 .env / 环境变量 → DB_PATH、APP_NAME、APP_VERSION、APP_ENV、SESSION_NAME',
                    '载入 core/helpers.php、Database.php、Model.php、Controller.php、Router.php → session_name() + session_start()',
                    'new Router() → require app/routes.php（路由的唯一来源）→ $router->dispatch(去掉 URL_ROOT 前缀后的 URI)',
                    'Router 匹配顺序：先完全相等，再正则匹配 {param}；表单用隐藏字段 _method 伪造 PUT/DELETE',
                    '控制器：requireAuth()/requireRole()/authorizeResource() → 调模型 → $this->view(模板, 数据, 布局)，视图先渲染成 $content 再套 layouts/main.php',
                    '未匹配：返回 errors/404.php（HTTP 404）；布局缺失时直接输出视图（用于 text/plain 之类的裸输出）',
                ],
                'rules' => [
                    '视图不查库、不实例化模型：控制器把需要的数组都传进去（附件、关联列表都算）',
                    '所有 SQL 走 PDO 预处理；所有 POST/PUT/DELETE 表单带 csrf_token，verifyCsrf() 失败直接 419',
                    '一句话主线：线索 lead → 商机 deal → 客户 customer → 订单 order（AI 提示词与本页同源，见 AppMap::forPrompt()）',
                ],
            ],
            [
                'title' => '线索 Lead：先线索，再商机',
                'steps' => [
                    'new（新建）→ contacted（已联系）→ qualified（已确认=已转商机）｜lost（流失，带 lost_reason）',
                    '「转为商机」LeadController::convert()：开事务 → 建客户（复制联系方式/国家/城市/地址/微信/WhatsApp，status=active，owner_id=当前用户）→ 用原始线索建商机（stage=open）→ 回写线索 status=qualified、customer_id、conversion_time；任一步失败整体回滚',
                    '「标记流失」LeadController::markLost()：status=lost + lost_reason（8 选 1，必填）+ lost_at',
                    '「重新激活」Lead::reactivate()：回到 contacted，并清空 lost_reason / lost_at',
                    '列表筛选：全部 / 新建 / 已联系 / 已确认 / 已流失；「流失原因」这一列只在 已流失 标签页出现',
                ],
                'rules' => [
                    'leads.customer_id 为空 = 还没转成客户的线索；不为空且 status=qualified = 已转（客户详情页据此显示“来源线索”）',
                    '删除线索只删线索：不会级联删掉它转出来的客户和商机',
                    '线索列表每页 15 条，分页用 views/partials/_pagination.php',
                ],
            ],
            [
                'title' => '商机 Deal：看板推进、成交生成订单、丢单归档',
                'steps' => [
                    '阶段：open → proposal → negotiation → closed_won｜closed_lost；看板显示前 4 列（没有丢单列）',
                    '每次进入新阶段都会写对应时间戳列：stage_open_at / stage_proposal_at / stage_negotiation_at / stage_closed_won_at / stage_closed_lost_at',
                    '改成 closed_won：DealController::update() → 自动建订单（OrderController::createFromDeal → Order）→ 订单编号由 Order::generateOrderNumber() 生成 ORD-{年}-NNN（按当年最后一条 +1）→ 金额取商品明细合计 → 商机的附件复制到订单 → 商机保留在看板“成交”列，不归档',
                    '改成 closed_lost：自动归档 archived=1 + archived_at，从看板移出',
                    '已归档页 /deals/archived → 「恢复」unarchive()：archived=0 且阶段重置回 open（可重新跟进）',
                ],
                'rules' => [
                    'deals.value 是预估金额，orders.amount 是实际金额：成交自动建单时以后者为准（由明细汇总）',
                    '商机/订单/客户/线索的 owner_id 只存用户 ID，姓名读取时 JOIN users —— 改账号资料即全站同步',
                ],
            ],
            [
                'title' => '订单 Order：发货链路与报价明细',
                'steps' => [
                    '状态：pending → confirmed → processing → shipped → delivered → completed，或 cancelled',
                    '付款状态：unpaid → partial → paid',
                    '明细 order_items：product_name/sku/quantity/unit_price/subtotal/unit/notes/sort_order；保存订单时整表重建（同步），订单金额 = Σsubtotal',
                    '附件与收货地址在订单上；客户详情页汇总该客户的全部订单',
                ],
                'rules' => [
                    'order_number 唯一（UNIQUE）；Order::generateOrderNumber() 按“当年最后一条”推算而不是 MAX(id)，删掉当年最后一条会重新占用该号',
                ],
            ],
            [
                'title' => '客户 Customer 与跟进 / 动态 / 附件',
                'steps' => [
                    '客户详情：基本信息 + 商机 + 订单 + 关联线索 + 跟进记录（比价类询价）+ 活动记录 + 附件',
                    '跟进 follow_ups.type：price_comparison｜no_response｜follow_up｜other，带 next_action/next_date',
                    '活动 activities：由「添加备注」写入（type=note，user_id=操作人）',
                    '搜索 /customers?q=：姓名、公司、邮箱、电话、WhatsApp、微信、国家、备注（LIKE 匹配，OR 关系）',
                ],
                'rules' => [
                    '附件规则：允许 图片(jpg/png/gif/webp) / pdf / Excel(xls/xlsx/ods) / csv / zip / rar，单文件 ≤ 20MB，存 public/uploads/attachments/，该目录 .htaccess 禁执行脚本',
                    '删除客户 CASCADE 连带其商机、订单、跟进、活动；用户被删只把 owner_id/user_id 置 NULL',
                ],
            ],
            [
                'title' => '权限与数据归属',
                'steps' => [
                    '角色只有两种：admin、sales；登录/注册写入 session，session_regenerate_id 防固定',
                    '登录失败 5 次锁 60 秒（计数存 Session，换浏览器即重置）',
                    '应用级设置（系统名称、货币、AI 配置）仅 admin：requireRole(\'admin\')',
                    'helpers 里备好 canManageResource($ownerId)：admin 放行；owner_id 为 NULL/0 视为公海人人可操作；否则必须等于当前用户',
                ],
                'rules' => [
                    '⚠ 现状：业务控制器（客户/线索/商机/订单）目前只做 requireAuth()，没有调用 canManageResource()/authorizeResource() —— 也就是说任何登录用户都能看到并编辑全部业务数据，负责人只是归属标签。要收紧就在这些控制器的 update/destroy 上加 authorizeResource()（行为变更，会影响现有使用习惯，需要先定）。',
                    '唯一强制该规则的地方是 AI 助手：Ai::validatePlan() 会用 canManageResource() 拒绝越权计划，并给模型的 ID 快照按此过滤',
                    '稳定编号 public_code：customers=CUS-、leads=LEAD-、deals=DEAL- + 六位零填充 id，由 Model::publicCode() 在 create() 后写入（id 先自增，编号依赖 id）；orders 用自己的 order_number',
                    '为什么用编号而不是裸 id：① 三类记录都有 id=7 时，人说“7 号”会歧义 ② 编号可念可抄、长度固定 ③ 关键防幻觉：模型编一个 CUS-999999 也会因为查不到而被拒，而不是误改一条无关记录',
                    '老数据兜底：Model::codeOf() 在列为空时按同一规则推导，因此界面与提示词永不为空；回填只在迁移 007 做一次（前缀拼接六位零填充 id）',
                    'AI 配置与密钥：api key 永不回显（只给掩码），留空表示不修改，恢复默认不会清除密钥',
                ],
            ],
            [
                'title' => 'AI 助手能做什么（权限矩阵）',
                'steps' => [
                    '查询（read）：search_records / get_record —— 立即执行、不写库、不需确认，结果写进 ai_actions；可搜索范围是 Ai::searchSurfaces() 白名单：线索/客户/商机/订单/跟进/动态/AI 记录；支持关键词 q 与字段条件 country / status / stage / owner（q 可留空，但至少要给一个条件；用户明确说“所有/全部”时才写 all:true 取整表），并返回精确总数，所以「现在有多少客户」能一句答准',
                    '多轮（先查再删）：Ai::complete() 是一个最多 Ai::MAX_TOOL_ROUNDS=3 轮的循环 —— 模型只要查询动作，服务端就当场执行（只读），把真实编号以 <tool_results> 回灌，模型下一轮再针对这些编号出写/删计划，所以「删除印度所有客户」「删掉名字含 armtek 的客户及其线索/商机/订单」这类指令能成立',
                    '写入（write）：create_lead / create_customer / create_deal / add_follow_up / update_lead / update_lead_status / update_customer / update_deal / update_deal_stage / update_order —— 预览确认模式下等你点“确认执行”，自动执行模式下直接落库',
                    '删除（delete）：delete_lead / delete_deal / delete_order / delete_customer / delete_ai_request —— 无论哪种模式都必须人工“确认执行”，参数里强制 confirm:true + reason',
                ],
                'rules' => [
                    '搜索只碰 Ai::searchSurfaces() 列出的表：app_settings 与 users 根本不在清单里，所以密钥、密码散列不会被 AI 读到；LIKE 的 % 与 _ 被转义，“100%”不会变成全表扇描',
                    '改与删的归属检查在 Ai::validatePlan() → checkRecordRef() → canManageResource()：销售动不了别人负责的行，admin 可动全部；被拦的动作一条都不会写库',
                    '删除前会算“连带影响”（deleteImpact）：删客户会列出名下线索/商机/订单/跟进/附件数量，预览页面直接给人看；执行后被删内容以快照写进 ai_actions.result_json，可追责',
                    '客户删除的级联与页面一致（LeadController/CustomerController/DealController@destroy 同一套语义）：删客户带走其线索/商机/订单；删商机只解除订单的 deal_id；附件行与磁盘文件一并清理（页面删除负不了文件这一项，AI 路径会清）',
                    '总开关：设置→AI 助手→“允许 AI 删除数据”（ai_allow_delete，也可用 AI_ALLOW_DELETE 环境变量覆盖），关掉后 delete_* 直接被判为校验失败，查询与修改不受影响',
                    '批量上限与背书都在服务端说：一个计划最多 Ai::MAX_DELETES=20 个删除动作，超出整批拒绝；≥2 个删除动作还必须有本轮真的查过库作背书（Ai::BULK_DELETE_NEEDS_QUERY，用户点名编号时除外）——实测模型会凭名字猜国籍，把伊拉克/埃及客户当成印度客户删，光靠提示词祈祷挡不住',
                    'AI 的可写字段不是手写清单，而是 Ai::fieldsFor() 读表结构生成，提示词、参数校验、真正落库三处同源：库里加一列 AI 就能写一列。上一版手写的参数表漏了 source_country 等列，于是出现「线索没有来源国家字段」这种根本不存在的拒绝。系统自维护的列（编号 public_code、单号 order_number、created_at/updated_at、stage_*_at / lost_at / archived_at、跟进人 user_id）在 Ai::PROTECTED_COLUMNS 里排除；改 status / stage / archived 时由系统连带写对应时间戳，与人工操作同一套语义；可空列传空字符串即清空，NOT NULL 且无默认值的列拒绝清空',
                    '国家筛选是等值 OR，不是模糊匹配：Ai::countryGroups() 把一种说法展开成该国全部写法 —— 库里 source_country 中英混写（印度、埃及、伊拉克 与 United States 并存），单向映射会查出 0 条模型就开始猜；而用 LIKE 模糊匹配又会把印度尼西亚当成印度，批量删除时就是误删',
                    'AI 能删自己的历史：delete_ai_request 只能删自己发起的记录（admin 可删任意），但不能删“正在执行的这一条”，避免执行完就把自己的审计链抹掉',
                ],
            ],
            [
                'title' => '设置与“一个人只存一份”规则',
                'steps' => [
                    '设置四个页签：个人信息 / 应用信息(管理员) / AI 助手(管理员) / 修改密码，见 SettingController',
                    '个人资料写入 users 表：姓名、邮箱、职位、电话、WhatsApp、备注；密码用 password_hash 重新散列',
                    '其他表一律不存人的副本，只存 owner_id / user_id / uploaded_by，读取时 JOIN users',
                    '因此改名后，客户/线索/商机/订单的负责人、跟进操作人、附件上传人、顶栏用户名立即全部一致（SettingTest 有断言禁止业务表新增 *_name 副本列）',
                ],
                'rules' => [
                    '应用信息存在 app_settings（键值表），读取入口 appSetting()/appName()/money()/appCopyright()，每请求一次查询 + 静态缓存，表缺失时退回 Setting::defaults()',
                    '环境变量优先于库中设置：APP_ENV、DB_PATH、AI_ENABLED、AI_PROVIDER、AI_MODEL、AI_BASE_URL、AI_API_KEY、AI_MODE、AI_ALLOW_DELETE —— 密钥放 .env 就不落库',
                    '“恢复默认”按页签分组（Setting::keysInGroup()），且任何重置都不会清除密钥；密钥只能靠“清除已保存的 API Key”勾选框显式删除',
                ],
            ],
            [
                'title' => '数据库迁移与结构变更',
                'steps' => [
                    '唯一入口 php database/migrate.php（幂等，可重复执行；--status 只查看；--db= 指定文件）',
                    '先整体重放 database/schema.sql（权威基线：CREATE … IF NOT EXISTS + INSERT OR IGNORE 种子）→ 缺失的表/索引/触发器/种子自愈补齐',
                    '再按文件名顺序执行 database/migrations/NNN_*.sql 中未登记在 _migrations 的增量',
                    '纯加列增量若发现列已存在（基线已含）→ 打印 skipped: … 并只登记，避免 duplicate column name；含建表/改约束的增量照常执行',
                    '最后自检：期望表齐全（缺表 exit 1）',
                ],
                'rules' => [
                    '加整张新表 / 索引 / 触发器 → 只写 schema.sql，不要建增量文件',
                    '改已有表（加列）→ 建 NNN_*.sql 增量，同时同步 schema.sql，新旧库才会收敛到同一结构',
                    '给 users 加列时不要给 NOT NULL/非常量默认值：SQLite 的 ADD COLUMN 不接受非常量默认',
                    'SQLite 的 datetime(\'now\') 是 UTC，而应用用 Asia/Shanghai，见下方“时区”一条',
                ],
            ],
        ];
    }

    /** @return array<int,array{title:string,body:string}> */
    public static function conventions(): array
    {
        $php = self::phpInfo();
        $out = [
            [
                'title' => '零依赖的边界',
                'body'  => '不用 Composer、不用框架包：core/ 里 Router、Controller、Model、Database、helpers、autoloader、AiClient 就是全部基础设施。HTTP 一律走 PHP 流（不依赖 curl 扩展）。'
                    . ' 本机实测：openssl=' . ($php['extensions']['openssl'] ? '已启用' : '未启用')
                    . '、curl 扩展=' . ($php['extensions']['curl'] ? '有' : '无')
                    . '、mbstring=' . ($php['extensions']['mbstring'] ? '有' : '无')
                    . '、出站协议=' . implode('/', $php['transports']) . '。',
            ],
            [
                'title' => '字符串计数必须走 helpers',
                'body'  => '禁止直接调用 mb_*（视图里一个 mb_strimwidth 就是致命错误）。用 textLength()/textTrim()/textClip()，它们在 mbstring 缺失时回退 iconv/PCRE。tests/cases/AutoloadViewTest.php 会扫全仓把违规揪出来。',
            ],
            [
                'title' => '⚠ 时间戳两种时区混存',
                'body'  => 'schema 的 DEFAULT/TRIGGER 用 SQLite datetime(\'now\')，那是 UTC；而 PHP 侧 date(\'Y-m-d H:i:s\')（lost_at、conversion_time、archived_at、stage_*_at、users.updated_at…）写的是 '
                    . $php['timezone'] . '，与 UTC 相差 ' . (int) $php['utc_offset'] . ' 小时。列表/详情用 formatDate() 直接按字面量解析，不做时区换算 —— 所以同一行里 created_at 与 lost_at 可能差 8 小时，别把它当数据错乱。统一成一个时区（建议全存 UTC、显示时换算）是个待办。',
            ],
            [
                'title' => '金额与文案',
                'body'  => '金额统一用 money()（货币符号是应用设置，不再硬编码 $）。状态徽标统一用 statusBadge()（中英映射都在那一处）。视图里不要写死系统名，用 appName()。',
            ],
            [
                'title' => '源码文件头与版权',
                'body'  => '每个源文件头部一行 ' . APP_COPYRIGHT . ' — ' . APP_RIGHTS . '；CopyrightHeaderTest 扫描全仓，新增文件漏盖会直接红。界面上展示的版权行是 app_settings.copyright_notice，可被部署方改写。',
            ],
            [
                'title' => '新增一个资源（固定四步）',
                'body'  => '1) schema.sql 建表 + php database/migrate.php；2) app/models/Xxx.php extends Model（设 protected string $table）；3) app/controllers/XxxController.php extends Controller（index/create/store/edit/update/destroy）；4) app/routes.php 注册 + app/views/xxx/*.php。其余（路由、库访问、布局、CSRF、分页、自动加载）都由 core 提供，不用再碰别的文件。',
            ],
            [
                'title' => '测试约定',
                'body'  => 'php tests/run.php（全量）/ php tests/run.php Order（按名过滤）/ 直接跑单个用例文件。每个用例文件独立进程 + 独立临时库（由真实 migrate.php 建库，所以迁移工具本身每次都被测到）。禁止联网与真实密钥：HTTP 用注入的假 transport，AI 用内置演示模型。',
            ],
        ];
        return $out;
    }

    // ---------------------------------------------------------------- render

    /**
     * Plain-text rendering (for /help/context and for the AI prompt).
     *
     * @param array<int,string>|null $sections subset; null = everything
     */
    public static function toText(?array $sections = null, int $limit = 0): string
    {
        $map = self::all();
        $pick = static fn(string $key): bool => $sections === null || in_array($key, $sections, true);
        $lines = [];

        if ($pick('app')) {
            $a = $map['app'];
            $lines[] = '# ' . $a['name'] . ' (' . $a['name_en'] . ') v' . $a['version'] . ' — ' . APP_RIGHTS;
            $lines[] = 'PHP ' . $map['php']['version'] . ' / SQLite ' . $map['php']['sqlite']
                . ' / 环境 ' . $a['env'] . ' / 时区 ' . $map['php']['timezone']
                . ' / https出站 ' . ($map['php']['https'] ? '可用' : '不可用(需 openssl)');
            $lines[] = '';
        }

        if ($pick('flows')) {
            $lines[] = '## 业务与流程';
            foreach ($map['flows'] as $flow) {
                $lines[] = '### ' . $flow['title'];
                foreach ($flow['steps'] as $s) { $lines[] = '  - ' . $s; }
                foreach ($flow['rules'] as $r) { $lines[] = '  * ' . $r; }
                $lines[] = '';
            }
        }

        if ($pick('schema')) {
            $lines[] = '## 数据表（实时从 SQLite 读出）';
            foreach ($map['schema'] as $table => $info) {
                $cols = [];
                foreach ($info['columns'] as $c) {
                    $one = $c['name'] . ':' . strtolower((string) $c['type']);
                    if ((int) $c['pk'] > 0) { $one .= '/PK'; }
                    if ((int) $c['notnull'] > 0 && (int) $c['pk'] === 0) { $one .= '/NOT NULL'; }
                    $cols[] = $one;
                }
                $lines[] = $table . '（' . $info['rows'] . ' 行）: ' . implode(', ', $cols);
                if ($info['foreign']) { $lines[] = '  FK: ' . implode(' | ', $info['foreign']); }
                if ($info['checks']) { $lines[] = '  枚举: ' . implode(' | ', $info['checks']); }
            }
            $lines[] = '';
        }

        if ($pick('routes')) {
            $lines[] = '## 路由（app/routes.php 实际注册）';
            foreach ($map['routes'] as $r) {
                $lines[] = '  ' . str_pad($r['method'], 6) . str_pad($r['path'], 46) . $r['handler'];
            }
            $lines[] = '';
        }

        if ($pick('settings')) {
            $lines[] = '## 设置项（app_settings；同名环境变量优先）';
            foreach ($map['settings'] as $s) {
                $lines[] = '  ' . str_pad($s['key'], 17) . ($s['secret'] ? '[密钥]' : '')
                    . ' 默认=' . $s['default'] . '；env ' . $s['env'];
            }
            $lines[] = '';
        }

        if ($pick('ai_tools')) {
            $lines[] = '## AI 工具白名单（Ai::tools() 实际定义）';
            foreach ($map['ai_tools'] as $t) {
                $ps = [];
                foreach ($t['params'] as $p) {
                    $ps[] = $p['name'] . ':' . $p['type'] . ($p['required'] ? '*' : '')
                        . ($p['options'] ? '(' . implode('|', $p['options']) . ')' : '');
                }
                $lines[] = '  ' . $t['name'] . ' [' . $t['label'] . '] ' . implode(', ', $ps);
            }
            $lines[] = '';
        }

        if ($pick('ai_models')) {
            $lines[] = '## AI 服务商预设（AiClient::providers()）';
            foreach ($map['ai_models'] as $p) {
                $lines[] = '  ' . str_pad($p['key'], 12) . $p['base'] . ' 默认=' . $p['default']
                    . ($p['models'] ? ' 可选=' . implode('/', $p['models']) : '')
                    . ($p['fast'] ? ' 快速模式=' . $p['fast'] : '');
            }
            $lines[] = '';
        }

        if ($pick('conventions')) {
            $lines[] = '## 约定与已知坑';
            foreach ($map['conventions'] as $c) {
                $lines[] = '### ' . $c['title'];
                $lines[] = '  ' . $c['body'];
            }
            $lines[] = '';
        }

        if ($pick('tests')) {
            $total = array_sum(array_column($map['tests'], 'tests'));
            $lines[] = '## 测试（tests/cases，' . count($map['tests']) . ' 个用例文件 / ' . $total . ' 个 test_* 函数）';
            foreach ($map['tests'] as $t) {
                $lines[] = '  ' . str_pad($t['name'], 22) . $t['tests'] . ' 项';
            }
        }

        $text = implode("\n", $lines);
        return $limit > 0 && textLength($text) > $limit ? textTrim($text, $limit) . '…' : $text;
    }

    /**
     * Enum columns harvested from the live CHECK constraints, e.g.
     * `leads.status => new|contacted|qualified|lost`.
     *
     * @return array<string,string>
     */
    public static function enums(): array
    {
        return Schema::enums();
    }

    private static function enumsLegacy(): array
    {
        $out = [];
        foreach (self::schema() as $table => $info) {
            foreach ($info['checks'] as $check) {
                if (preg_match("~^(\w+)\s+IN\s*\((.*)\)$~i", $check, $m)) {
                    $vals = array_map(static fn($v) => trim((string) $v, " \n'"), explode(',', $m[2]));
                    $out[$table . '.' . $m[1]] = implode('|', $vals);
                }
            }
        }
        return $out;
    }

    /**
     * The AI prompt map — deliberately tiny, because it ships with EVERY request:
     * its length is the user's waiting time. Only the enum values survive the cut
     * (a model cannot guess them); column lists cannot, because each tool already
     * declares its own arguments. No routes, no settings, no secrets, no dev notes.
     */
    public static function forPrompt(int $limit = 900): string
    {
        $lines = ['业务主线：线索 lead → 商机 deal → 客户 customer → 订单 order。'];

        foreach (self::enums() as $column => $values) {
            if (str_starts_with($column, 'users.') || str_starts_with($column, 'ai_actions.')
                || str_starts_with($column, 'attachments.')) {
                continue;
            }
            $lines[] = $column . ' = ' . $values;
        }

        $lines[] = '人员只存于 users 一行：业务表只有 owner_id / user_id / uploaded_by，没有姓名字段可写。';
        $lines[] = '每条记录都有稳定编号：客户 CUS-000007、线索 LEAD-000007、商机 DEAL-000007、订单用 order_number（ORD-2026-007）。引用记录一律优先用编号（*_id 参数接受编号或数字 ID），编号请从 <found>、数据快照或搜索结果里原样复制。';
        $lines[] = '销售只能操作自己负责或未分配（公海）的记录；删除类动作必须带 confirm:true 与 reason，且永远需要人工“确认执行”；日期写 YYYY-MM-DD，金额只写数字。';

        return textTrim(implode("\n", $lines), $limit);
    }
}
