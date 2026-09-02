<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">使用说明</h3>
</div>

<!-- 项目概述 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>项目概述</h5>
    <p>本系统是一个轻量级客户关系管理（CRM）系统，帮助销售团队管理客户信息、跟踪销售线索、推进商机成交、管理订单。系统采用 MVC 架构，使用 PHP + SQLite 构建。</p>
    <div class="row g-3 mt-2">
        <div class="col-md-3 col-6">
            <div class="border rounded p-3 text-center h-100">
                <i class="bi bi-magnet-fill fs-2 text-info"></i>
                <h6 class="mt-2">线索管理</h6>
                <small class="text-muted">跟踪潜在客户信息，判断意向后转商机</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="border rounded p-3 text-center h-100">
                <i class="bi bi-lightning-fill fs-2 text-warning"></i>
                <h6 class="mt-2">商机管理</h6>
                <small class="text-muted">有真实采购意向的询价，推进成交</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="border rounded p-3 text-center h-100">
                <i class="bi bi-receipt fs-2 text-success"></i>
                <h6 class="mt-2">订单管理</h6>
                <small class="text-muted">商机成交后自动生成订单，管理商品明细</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="border rounded p-3 text-center h-100">
                <i class="bi bi-people-fill fs-2 text-primary"></i>
                <h6 class="mt-2">客户管理</h6>
                <small class="text-muted">管理客户信息、商机、订单、跟进记录</small>
            </div>
        </div>
    </div>
</div>

<!-- 核心业务流程 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-diagram-3 me-2"></i>核心业务流程</h5>
    <p class="mb-3">系统的业务流程遵循"先线索，再商机，再订单"的原则：</p>
    
    <div class="bg-light p-3 rounded mb-3">
        <pre class="mb-0" style="font-size: 13px; overflow-x: auto;">
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              完整业务流程                                        │
└─────────────────────────────────────────────────────────────────────────────────┘

    ┌──────────┐      ┌──────────┐      ┌──────────┐      ┌──────────┐
    │   线索   │ ───▶ │   商机   │ ───▶ │   订单   │ ───▶ │  完成交付 │
    │  (Lead)  │      │  (Deal)  │      │ (Order)  │      │          │
    └──────────┘      └──────────┘      └──────────┘      └──────────┘
         │                 │                 │
         ▼                 ▼                 ▼
    ┌──────────┐      ┌──────────┐      ┌──────────┐
    │  标记流失 │      │   丢单   │      │  取消订单 │
    │   (Lost)  │      │          │      │          │
    └──────────┘      └──────────┘      └──────────┘


    ┌─────────────────────────────────────────────────────────────────────┐
    │                         线索生命周期                                 │
    └─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                     ┌─────────────────┐
                     │  新建线索 (new)  │
                     │  陌生客户信息    │
                     └────────┬────────┘
                              │
                              ▼
                     ┌─────────────────┐
                     │ 已联系(contacted)│
                     │ 初步沟通了解需求  │
                     └────────┬────────┘
                              │
            ┌─────────────────┼─────────────────┐
            │                 │                 │
            ▼                 ▼                 ▼
   ┌───────────────┐  ┌───────────────┐  ┌───────────────┐
   │ 有真实采购意向 │  │ 只是比价/暂无 │  │  符合流失条件 │
   │ 采购时间明确   │  │ 真实采购意愿   │  │              │
   │ 采购数量确定   │  │              │  │              │
   └───────┬───────┘  └───────┬───────┘  └───────┬───────┘
           │                  │                  │
           ▼                  ▼                  ▼
   ┌───────────────┐  ┌───────────────┐  ┌───────────────┐
   │   转为商机     │  │  持续跟进     │  │  标记流失     │
   │  (qualified)  │  │  (contacted)  │  │   (lost)     │
   └───────┬───────┘  └───────────────┘  └───────┬───────┘
           │                                      │
           ▼                                      ▼
   ┌───────────────┐                      ┌───────────────┐
   │ 自动创建客户  │                      │ 记录流失原因  │
   │ 自动创建商机  │                      │ 可重新激活    │
   └───────────────┘                      └───────────────┘
        </pre>
    </div>

    <h6>详细说明：</h6>
    <ol class="mb-0">
        <li class="mb-2"><strong>发现线索</strong> — 通过网站、推荐、市场活动等渠道获取潜在联系人信息，创建线索记录。</li>
        <li class="mb-2"><strong>跟进确认</strong> — 联系线索中的联系人，确认其真实需求：
            <ul>
                <li><strong>有真实采购意向</strong>：有明确的采购时间、数量、预算 → 转为商机</li>
                <li><strong>只是比价/暂无意向</strong>：保持联系状态，持续跟进</li>
                <li><strong>符合流失条件</strong>：标记流失并记录原因</li>
            </ul>
        </li>
        <li class="mb-2"><strong>转为商机</strong> — 线索确认后，点击"转为商机"按钮，系统将<strong>自动创建客户</strong>并生成一条商机记录。</li>
        <li class="mb-2"><strong>推进成交</strong> — 在商机页面推进商机阶段（进行中 → 方案阶段 → 谈判中 → 成交）。</li>
        <li class="mb-0"><strong>生成订单</strong> — 商机成交时，系统<strong>自动创建订单</strong>，并可填写商品明细（产品名、数量、单价等）。</li>
    </ol>
</div>

<!-- 订单管理 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-receipt me-2"></i>订单管理</h5>

    <h6 class="text-muted">订单来源</h6>
    <p>订单有两种创建方式：</p>
    <ol>
        <li><strong>自动创建（推荐）</strong> — 编辑商机，将阶段改为"成交"时，系统自动创建订单，并可填写商品明细</li>
        <li><strong>手动创建</strong> — 在订单列表页点击"新建订单"按钮，手动填写订单信息和商品明细</li>
    </ol>

    <h6 class="text-muted">订单状态</h6>
    <table class="table table-sm mb-3">
        <thead><tr><th>状态</th><th>含义</th></tr></thead>
        <tbody>
            <tr><td><span class="badge text-bg-secondary">待确认</span></td><td>订单刚创建，等待确认</td></tr>
            <tr><td><span class="badge text-bg-info">已确认</span></td><td>订单已确认，准备处理</td></tr>
            <tr><td><span class="badge text-bg-warning">处理中</span></td><td>订单正在处理/备货中</td></tr>
            <tr><td><span class="badge text-bg-primary">已发货</span></td><td>商品已发出</td></tr>
            <tr><td><span class="badge text-bg-success">已送达</span></td><td>商品已送达客户</td></tr>
            <tr><td><span class="badge text-bg-success">已完成</span></td><td>订单完成交付</td></tr>
            <tr><td><span class="badge text-bg-danger">已取消</span></td><td>订单已取消</td></tr>
        </tbody>
    </table>

    <h6 class="text-muted">付款状态</h6>
    <table class="table table-sm mb-3">
        <thead><tr><th>状态</th><th>含义</th></tr></thead>
        <tbody>
            <tr><td><span class="badge text-bg-danger">未付款</span></td><td>客户尚未付款</td></tr>
            <tr><td><span class="badge text-bg-warning">部分付款</span></td><td>客户已支付部分款项</td></tr>
            <tr><td><span class="badge text-bg-success">已付款</span></td><td>客户已完成付款</td></tr>
        </tbody>
    </table>

    <h6 class="text-muted">商品明细</h6>
    <p>每个订单可以包含多条商品明细，每条明细包含：</p>
    <ul>
        <li><strong>商品名称</strong> — 产品或服务名称（必填）</li>
        <li><strong>规格/SKU</strong> — 产品规格编号（可选）</li>
        <li><strong>数量</strong> — 采购数量</li>
        <li><strong>单位</strong> — 计量单位（件、套、台、次、天、年等）</li>
        <li><strong>单价</strong> — 单个价格</li>
        <li><strong>小计</strong> — 数量 × 单价（自动计算）</li>
        <li><strong>备注</strong> — 补充说明（可选）</li>
    </ul>
    <p class="mb-0"><strong>订单金额</strong>会根据商品明细自动计算合计。</p>
</div>

<!-- 模型关系图 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-diagram-2 me-2"></i>数据模型关系</h5>
    <div class="bg-light p-3 rounded">
        <pre class="mb-0" style="font-size: 13px;">
┌────────┐     ┌────────┐     ┌────────┐     ┌────────────┐
│  用户  │◀────│  线索  │────▶│  客户  │◀────│    商机    │
│ (User) │     │ (Lead) │     │(Customer)    │   (Deal)   │
└────────┘     └────────┘     └───┬────┘     └─────┬──────┘
    │                             │                 │
    │                             │                 │
    │              ┌──────────────┼─────────────────┘
    │              │              │
    │              ▼              ▼
    │         ┌────────┐    ┌────────────┐
    └────────▶│  订单  │◀───│ 订单明细   │
              │(Order) │    │(OrderItem) │
              └────────┘    └────────────┘
                  │
    ┌─────────────┼─────────────┐
    │             │             │
    ▼             ▼             ▼
┌────────┐  ┌────────┐  ┌────────────┐
│跟进记录│  │活动记录│  │  商机      │
│FollowUp│  │Activity│  │  (Deal)    │
└────────┘  └────────┘  └────────────┘
        </pre>
    </div>
</div>

<!-- 商机 vs 线索 vs 跟进记录 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-question-diamond me-2"></i>商机 vs 线索 vs 跟进记录 vs 订单</h5>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>类型</th>
                    <th>定义</th>
                    <th>特点</th>
                    <th>存在位置</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge text-bg-info">线索</span></td>
                    <td>潜在客户信息</td>
                    <td>尚未转化为客户</td>
                    <td>线索列表</td>
                </tr>
                <tr>
                    <td><span class="badge text-bg-warning">商机</span></td>
                    <td>有真实采购意向的询价</td>
                    <td>有采购时间、意愿、数量</td>
                    <td>商机看板</td>
                </tr>
                <tr>
                    <td><span class="badge text-bg-success">订单</span></td>
                    <td>商机成交后的正式订单</td>
                    <td>包含商品明细、付款状态</td>
                    <td>订单列表</td>
                </tr>
                <tr>
                    <td><span class="badge text-bg-secondary">跟进记录</span></td>
                    <td>比价或无采购意愿的询价</td>
                    <td>记录客户比价情况</td>
                    <td>客户详情页</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 仪表盘 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-speedometer2 me-2"></i>仪表盘</h5>
    <p>登录后首页即为仪表盘，提供全局数据概览：</p>
    <table class="table table-sm mb-0">
        <thead><tr><th>卡片</th><th>说明</th></tr></thead>
        <tbody>
            <tr><td>客户总数</td><td>系统中所有客户的数量</td></tr>
            <tr><td>商机管线</td><td>所有未结束商机的总金额</td></tr>
            <tr><td>订单总数</td><td>系统中所有订单的数量</td></tr>
            <tr><td>订单总额</td><td>所有订单的总金额</td></tr>
        </tbody>
    </table>
    <p class="mt-2 mb-0">下方还会显示最近的客户、商机、线索和订单列表。</p>
</div>

<!-- 线索管理 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-magnet me-2"></i>线索管理</h5>

    <h6 class="text-muted">线索列表</h6>
    <p>显示所有线索，顶部有状态筛选按钮（全部 / 新建 / 已联系 / 已确认 / 已流失）。</p>

    <h6 class="text-muted">线索状态说明</h6>
    <table class="table table-sm mb-3">
        <thead><tr><th>状态</th><th>含义</th><th>可执行操作</th></tr></thead>
        <tbody>
            <tr>
                <td><span class="badge text-bg-secondary">新建</span></td>
                <td>刚获取的线索，尚未联系</td>
                <td>编辑、删除</td>
            </tr>
            <tr>
                <td><span class="badge text-bg-info">已联系</span></td>
                <td>已与联系人取得初步联系</td>
                <td>转商机、标记流失、编辑</td>
            </tr>
            <tr>
                <td><span class="badge text-bg-primary">已确认</span></td>
                <td>已转为商机</td>
                <td>查看（只读）</td>
            </tr>
            <tr>
                <td><span class="badge text-bg-danger">已流失</span></td>
                <td>暂无机会</td>
                <td>重新激活、编辑</td>
            </tr>
        </tbody>
    </table>

    <h6 class="text-muted">线索转商机</h6>
    <p>对于已联系的线索，点击操作栏的绿色转换按钮 <i class="bi bi-arrow-right-circle text-success"></i>，系统将：</p>
    <ol>
        <li>根据线索中的联系人信息<strong>自动创建客户</strong></li>
        <li>创建一条对应的<strong>商机</strong>记录，关联到新客户</li>
        <li>将线索状态标记为"已确认"</li>
    </ol>

    <h6 class="text-muted">标记流失</h6>
    <p>对于暂无机会的线索，点击操作栏的黄色流失按钮 <i class="bi bi-x-circle text-warning"></i>，需要选择流失原因：</p>
    <div class="table-responsive">
        <table class="table table-sm mb-3">
            <thead><tr><th>流失原因</th><th>说明</th></tr></thead>
            <tbody>
                <tr><td>暂无需求</td><td>客户明确表示当前不需要</td></tr>
                <tr><td>已选竞品</td><td>客户已选择其他供应商</td></tr>
                <tr><td>预算不足</td><td>客户预算与报价差距较大</td></tr>
                <tr><td>需求不匹配</td><td>我们的产品/服务无法满足客户需求</td></tr>
                <tr><td>长期无响应</td><td>超过30天多次联系未果</td></tr>
                <tr><td>项目取消</td><td>客户项目取消或搁置</td></tr>
                <tr><td>联系不上</td><td>联系方式失效</td></tr>
                <tr><td>其他原因</td><td>其他情况</td></tr>
            </tbody>
        </table>
    </div>

    <h6 class="text-muted">重新激活</h6>
    <p class="mb-0">已流失的线索可以重新激活，点击操作栏的激活按钮 <i class="bi bi-arrow-counterclockwise text-success"></i>，线索将恢复为"已联系"状态，可继续跟进或转商机。</p>
</div>

<!-- 商机管理 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-lightning me-2"></i>商机管理</h5>

    <h6 class="text-muted">什么是商机？</h6>
    <p>商机是指<strong>有真实采购意向</strong>的询价，具备以下特征：</p>
    <ul>
        <li>有明确的采购时间</li>
        <li>有确定的采购数量</li>
        <li>有真实的采购意愿</li>
        <li>不是单纯的比价行为</li>
    </ul>

    <h6 class="text-muted">商机阶段</h6>
    <table class="table table-sm mb-3">
        <thead><tr><th>阶段</th><th>含义</th></tr></thead>
        <tbody>
            <tr><td><span class="badge text-bg-primary">进行中</span></td><td>初步接触，尚未出方案</td></tr>
            <tr><td><span class="badge text-bg-info">方案阶段</span></td><td>已提交方案或报价</td></tr>
            <tr><td><span class="badge text-bg-warning">谈判中</span></td><td>客户正在议价或走审批流程</td></tr>
            <tr><td><span class="badge text-bg-success">成交</span></td><td>成功签约，<strong>自动生成订单</strong></td></tr>
            <tr><td><span class="badge text-bg-danger">丢单</span></td><td>未能成交</td></tr>
        </tbody>
    </table>

    <h6 class="text-muted">商机成交自动创建订单</h6>
    <p>当商机阶段变为"成交"时，系统会<strong>自动创建订单</strong>：</p>
    <ol>
        <li>编辑商机，将阶段改为"成交"</li>
        <li>页面下方出现"商品明细"区域</li>
        <li>点击"添加商品"填写产品名称、数量、单价</li>
        <li>保存后系统自动创建订单，订单金额 = 商品明细合计</li>
    </ol>

    <h6 class="text-muted">新建商机</h6>
    <p class="mb-0">填写商机名称（必填）、选择客户（必填）、金额、阶段和预计成交日期。</p>
</div>

<!-- 客户管理 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-people me-2"></i>客户管理</h5>

    <h6 class="text-muted">客户的两种来源</h6>
    <ol>
        <li><strong>手动创建</strong> — 对于其他渠道推荐过来的真实客户信息（如朋友介绍、线下活动等），可以直接手动创建客户</li>
        <li><strong>线索转化</strong> — 线索转为商机时，系统会自动创建客户并关联线索信息</li>
    </ol>
    <p class="text-muted small">两种方式创建的客户在系统中没有区别，都可以创建商机、添加跟进记录。</p>

    <h6 class="text-muted">客户详情页</h6>
    <p>点击客户姓名进入详情页，包含以下信息：</p>
    <ul>
        <li><strong>联系信息</strong> — 邮箱、电话、地址、负责人</li>
        <li><strong>商机记录</strong> — 该客户的所有商机，包括金额、预计成交日期、阶段</li>
        <li><strong>订单记录</strong> — 该客户的所有订单，包括订单号、金额、状态</li>
        <li><strong>来源线索</strong> — 显示该客户是由哪条线索转化而来</li>
        <li><strong>跟进记录</strong> — 记录比价、无意向等跟进情况（核心功能）</li>
        <li><strong>活动记录</strong> — 时间线形式记录的电话、会议、备注等</li>
    </ul>

    <h6 class="text-muted">跟进记录（核心功能）</h6>
    <p>跟进记录用于记录客户<strong>没有真实采购意愿、只进行比价</strong>的询价情况：</p>
    <ul>
        <li><strong>比价询价</strong> — 客户只是在比价，没有真实采购意愿</li>
        <li><strong>无回复</strong> — 客户没有回复</li>
        <li><strong>跟进中</strong> — 正在持续跟进</li>
        <li><strong>其他</strong> — 其他情况</li>
    </ul>
    <p class="mb-0">每条跟进记录可以包含：标题、描述、下一步行动、下次跟进日期。</p>
</div>

<!-- 数据库迁移 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-database me-2"></i>数据库说明</h5>
    <p>系统使用 SQLite 数据库，数据库文件位于 <code>database/crm.sqlite</code>。</p>
    
    <h6 class="text-muted">全新安装</h6>
    <pre class="bg-light p-3 rounded small mb-3"><code>sqlite3 database/crm.sqlite &lt; database/schema.sql</code></pre>

    <h6 class="text-muted">从旧版本升级</h6>
    <p>如果已有数据库，运行迁移脚本添加新功能：</p>
    <pre class="bg-light p-3 rounded small mb-0"><code># 添加订单和订单明细表
php migrate.php</code></pre>
</div>

<!-- 常见问题 -->
<div class="card card-table p-4">
    <h5 class="mb-3"><i class="bi bi-question-circle me-2"></i>常见问题</h5>
    <div class="accordion" id="faqAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                    如何启动项目？
                </button>
            </h2>
            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    在项目根目录下运行 PHP 内置服务器：<br>
                    <code>php -S 127.0.0.1:3500 -t public router.php</code><br><br>
                    然后浏览器访问 <code>http://127.0.0.1:3500</code><br><br>
                    <small class="text-muted">注意：使用 <code>router.php</code> 可以正确处理 CSS、JS 等静态文件。</small>
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                    商机成交后如何生成订单？
                </button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    商机成交后会<strong>自动创建订单</strong>：<br><br>
                    1. 进入商机编辑页面<br>
                    2. 将阶段改为"成交"<br>
                    3. 页面下方出现"商品明细"区域<br>
                    4. 点击"添加商品"填写产品名称、数量、单价<br>
                    5. 保存后系统自动创建订单<br><br>
                    订单金额 = 所有商品明细的小计之和（自动计算）。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                    如何管理订单商品明细？
                </button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    在订单创建或编辑页面：<br><br>
                    1. 点击"添加商品"按钮添加新商品行<br>
                    2. 填写商品名称、规格/SKU、数量、单位、单价<br>
                    3. 小计会自动计算（数量 × 单价）<br>
                    4. 订单金额会自动更新为所有商品的合计<br><br>
                    可以点击每行右侧的删除按钮移除商品。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                    客户有哪几种创建方式？
                </button>
            </h2>
            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    客户有两种创建方式：<br><br>
                    <strong>1. 手动创建</strong><br>
                    适用于其他渠道推荐过来的真实客户信息（如朋友介绍、线下活动、老客户推荐等）。<br>
                    在客户列表页点击"新建客户"按钮即可。<br><br>
                    <strong>2. 线索转化</strong><br>
                    线索转为商机时，系统会自动创建客户。<br>
                    在客户详情页会显示"来源线索"信息。<br><br>
                    两种方式创建的客户在系统中没有区别。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                    比价客户应该怎么处理？
                </button>
            </h2>
            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    对于只是比价、没有真实采购意愿的客户：<br>
                    1. <strong>不要转为商机</strong> — 商机只用于有真实采购意向的询价<br>
                    2. <strong>保持线索状态</strong> — 继续在"已联系"状态跟进<br>
                    3. <strong>记录跟进情况</strong> — 转为商机后，在客户详情页的"跟进记录"中记录比价情况
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                    流失的线索可以重新激活吗？
                </button>
            </h2>
            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    可以。在线索列表页面，已流失的线索会显示"激活"按钮。点击后，线索将恢复为"已联系"状态，可以继续跟进或转为商机。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                    演示账号是什么？
                </button>
            </h2>
            <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    登录页底部有提示：<br>
                    邮箱：<code>admin@example.com</code><br>
                    密码：<code>password</code>
                </div>
            </div>
        </div>
    </div>
</div>
