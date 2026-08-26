<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">使用说明</h3>
</div>

<!-- 项目概述 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>项目概述</h5>
    <p>本系统是一个轻量级客户关系管理（CRM）系统，帮助销售团队管理客户信息、跟踪销售线索、推进商机成交。系统采用 MVC 架构，使用 PHP + MySQL 构建。</p>
    <div class="row g-3 mt-2">
        <div class="col-md-4">
            <div class="border rounded p-3 text-center">
                <i class="bi bi-people-fill fs-2 text-primary"></i>
                <h6 class="mt-2">客户管理</h6>
                <small class="text-muted">管理所有客户的基本信息、联系方式和活动记录</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 text-center">
                <i class="bi bi-magnet-fill fs-2 text-info"></i>
                <h6 class="mt-2">线索管理</h6>
                <small class="text-muted">跟踪潜在销售机会，从新建到确认的完整流程</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 text-center">
                <i class="bi bi-currency-dollar fs-2 text-warning"></i>
                <h6 class="mt-2">商机管理</h6>
                <small class="text-muted">看板视图管理商机，从进行中到成交/丢单</small>
            </div>
        </div>
    </div>
</div>

<!-- 核心业务流程 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-diagram-3 me-2"></i>核心业务流程</h5>
    <p class="mb-3">系统的三个核心模块之间存在紧密的关联关系，典型的工作流程如下：</p>
    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
        <span class="badge text-bg-info fs-6 px-3 py-2">线索</span>
        <i class="bi bi-arrow-right fs-4 text-muted"></i>
        <span class="badge text-bg-primary fs-6 px-3 py-2">客户</span>
        <i class="bi bi-arrow-right fs-4 text-muted"></i>
        <span class="badge text-bg-success fs-6 px-3 py-2">商机</span>
        <i class="bi bi-arrow-right fs-4 text-muted"></i>
        <span class="badge text-bg-success fs-6 px-3 py-2" style="background:#198754!important">成交</span>
    </div>
    <ol class="mb-0">
        <li class="mb-2"><strong>发现线索</strong> — 通过网站、推荐等渠道获取潜在客户信息，创建线索记录。</li>
        <li class="mb-2"><strong>关联客户</strong> — 将线索与系统中的客户关联（或先创建客户），确认线索有效性。</li>
        <li class="mb-2"><strong>转为商机</strong> — 线索确认后，点击"转为商机"按钮，自动生成一条商机记录。</li>
        <li class="mb-0"><strong>推进成交</strong> — 在看板中推进商机阶段（进行中 → 方案阶段 → 谈判中 → 成交）。</li>
    </ol>
</div>

<!-- 仪表盘 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-speedometer2 me-2"></i>仪表盘</h5>
    <p>登录后首页即为仪表盘，提供全局数据概览：</p>
    <table class="table table-sm mb-0">
        <thead><tr><th>卡片</th><th>说明</th></tr></thead>
        <tbody>
            <tr><td>客户总数</td><td>系统中所有客户的数量</td></tr>
            <tr><td>活跃客户</td><td>状态为"活跃"的客户数量</td></tr>
            <tr><td>待处理线索</td><td>状态不是"已流失"的线索数量</td></tr>
            <tr><td>商机管线</td><td>所有未结束商机的总金额</td></tr>
        </tbody>
    </table>
    <p class="mt-3 mb-0">下方还有三个列表，分别展示最近的客户、商机和线索，方便快速了解最新动态。</p>
</div>

<!-- 客户管理 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-people me-2"></i>客户管理</h5>

    <h6 class="text-muted">客户列表</h6>
    <p>显示所有客户，支持按姓名、公司、邮箱搜索。每行显示客户基本信息和负责人。</p>

    <h6 class="text-muted">新建 / 编辑客户</h6>
    <p>填写客户姓名（必填）、公司、邮箱、电话、地址、状态和备注。</p>

    <h6 class="text-muted">客户详情</h6>
    <p>点击客户姓名进入详情页，包含以下信息：</p>
    <ul>
        <li><strong>联系信息</strong> — 邮箱、电话、地址、负责人</li>
        <li><strong>关联线索</strong> — 该客户下的所有线索</li>
        <li><strong>关联商机</strong> — 该客户下的所有商机及金额</li>
        <li><strong>活动记录</strong> — 时间线形式记录的电话、会议、备注等</li>
    </ul>

    <h6 class="text-muted">活动记录</h6>
    <p>在客户详情页底部的输入框中，可以快速记录电话沟通、会议纪要或任何备注。每条记录会显示操作人和时间。</p>
</div>

<!-- 线索管理 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-magnet me-2"></i>线索管理</h5>

    <h6 class="text-muted">线索列表</h6>
    <p>显示所有线索，顶部有状态筛选按钮（全部 / 新建 / 已联系 / 已确认 / 已流失）。</p>

    <h6 class="text-muted">线索状态说明</h6>
    <table class="table table-sm mb-3">
        <thead><tr><th>状态</th><th>含义</th></tr></thead>
        <tbody>
            <tr><td><span class="badge text-bg-secondary">新建</span></td><td>刚获取的线索，尚未联系</td></tr>
            <tr><td><span class="badge text-bg-info">已联系</span></td><td>已与客户取得初步联系</td></tr>
            <tr><td><span class="badge text-bg-primary">已确认</span></td><td>确认有真实需求，可转为商机</td></tr>
            <tr><td><span class="badge text-bg-danger">已流失</span></td><td>客户无需求或已选择竞品</td></tr>
        </tbody>
    </table>

    <h6 class="text-muted">线索转商机</h6>
    <p>对于已确认的线索，需要先将其关联到一个客户，然后点击操作栏的绿色转换按钮 <i class="bi bi-arrow-right-circle text-success"></i>，系统会自动创建一条对应的商机记录。</p>
    <p class="text-muted small mb-0"><strong>注意：</strong>如果线索未关联客户，系统会提示"请先将此线索关联到客户再转换"。</p>
</div>

<!-- 商机管理 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-kanban me-2"></i>商机管理</h5>

    <h6 class="text-muted">看板视图</h6>
    <p>商机页面采用看板（Kanban）布局，按阶段分为五列：</p>
    <table class="table table-sm mb-3">
        <thead><tr><th>阶段</th><th>含义</th></tr></thead>
        <tbody>
            <tr><td><span class="badge text-bg-primary">进行中</span></td><td>初步接触，尚未出方案</td></tr>
            <tr><td><span class="badge text-bg-info">方案阶段</span></td><td>已提交方案或报价</td></tr>
            <tr><td><span class="badge text-bg-warning">谈判中</span></td><td>客户正在议价或走审批流程</td></tr>
            <tr><td><span class="badge text-bg-success">成交</span></td><td>成功签约</td></tr>
            <tr><td><span class="badge text-bg-danger">丢单</span></td><td>未能成交</td></tr>
        </tbody>
    </table>

    <h6 class="text-muted">新建商机</h6>
    <p>填写商机名称（必填）、选择客户（必填）、金额、阶段和预计成交日期。</p>

    <h6 class="text-muted">编辑 / 删除</h6>
    <p>每张商机卡片右侧有编辑和删除按钮。编辑时可修改所有字段，包括调整阶段以推进商机。</p>
</div>

<!-- 用户与权限 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-shield-lock me-2"></i>用户与权限</h5>
    <ul class="mb-0">
        <li class="mb-2"><strong>注册</strong> — 任何人都可以注册账号，新注册用户默认为"销售"角色。</li>
        <li class="mb-2"><strong>数据隔离</strong> — 每个用户只能看到自己创建的客户、线索和商机（通过 owner_id 字段实现）。</li>
        <li class="mb-0"><strong>退出登录</strong> — 点击右上角用户名下拉菜单中的"退出登录"按钮。</li>
    </ul>
</div>

<!-- 技术架构 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-gear me-2"></i>技术架构</h5>
    <div class="row g-3">
        <div class="col-md-6">
            <h6 class="text-muted">目录结构</h6>
            <pre class="bg-light p-3 rounded small mb-0"><code>crm/
├── app/
│   ├── config/config.php    # 配置文件（数据库、环境变量）
│   ├── core/
│   │   ├── Controller.php   # 控制器基类
│   │   ├── Database.php     # PDO 数据库封装
│   │   ├── Model.php        # 模型基类
│   │   ├── Router.php       # 路由器
│   │   └── helpers.php      # 全局辅助函数
│   ├── controllers/         # 各模块控制器
│   ├── models/              # 数据模型
│   ├── views/               # 视图模板
│   ├── routes.php           # 路由定义
│   └── bootstrap.php        # 启动引导
├── database/
│   └── schema.sql           # 数据库建表脚本
├── public/                  # Web 入口（index.php）
├── .env                     # 环境变量
└── .htaccess                # URL 重写规则</code></pre>
        </div>
        <div class="col-md-6">
            <h6 class="text-muted">技术栈</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td>后端语言</td><td>PHP 8.2+</td></tr>
                    <tr><td>数据库</td><td>MySQL 8.x</td></tr>
                    <tr><td>前端框架</td><td>Bootstrap 5.3</td></tr>
                    <tr><td>图标</td><td>Bootstrap Icons</td></tr>
                    <tr><td>架构模式</td><td>MVC（自研轻量框架）</td></tr>
                    <tr><td>安全机制</td><td>CSRF Token、密码哈希（bcrypt）</td></tr>
                </tbody>
            </table>
        </div>
    </div>
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
                    <code>php -S 127.0.0.1:3333 -t public</code><br>
                    然后浏览器访问 <code>http://127.0.0.1:3333</code>
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                    如何修改数据库连接？
                </button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    编辑项目根目录下的 <code>.env</code> 文件，修改以下变量：<br>
                    <code>DB_HOST</code>（主机）、<code>DB_PORT</code>（端口）、<code>DB_NAME</code>（数据库名）、<code>DB_USER</code>（用户名）、<code>DB_PASS</code>（密码）
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                    如何添加新字段？
                </button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    以给客户添加"网站"字段为例：<br>
                    1. 数据库执行 <code>ALTER TABLE customers ADD COLUMN website VARCHAR(255) NULL;</code><br>
                    2. 在 <code>app/views/customers/_form.php</code> 中添加输入框<br>
                    3. 在 <code>CustomerController.php</code> 的 <code>validate()</code> 方法中添加对应字段<br>
                    模型层会自动处理新字段的读写，无需修改 Model 文件。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                    演示账号是什么？
                </button>
            </h2>
            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    登录页底部有提示：<br>
                    邮箱：<code>admin@example.com</code><br>
                    密码：<code>password</code>
                </div>
            </div>
        </div>
    </div>
</div>
