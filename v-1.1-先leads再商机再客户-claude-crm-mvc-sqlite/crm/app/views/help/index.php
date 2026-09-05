<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">使用说明</h3>
</div>

<!-- 项目概述 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>项目概述</h5>
    <p><strong><?= e(appName()) ?></strong> 是一个轻量级客户关系管理（CRM）系统，帮助销售团队管理客户信息、跟踪销售线索、推进商机成交、管理订单；名称取“线索 → 商机 → 客户”三段行程之意。系统采用 MVC 架构，使用 PHP + SQLite 构建。</p>
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

<?php /* 技术参考：结构 / 数据字典 / 路由 / 设置 / AI / 约定 / 测试，全部实时生成 */
    if (isset($map)) {
        require APP_PATH . '/views/help/_tech.php';
    } ?>

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
    <p class="mb-0">列表默认列为：线索 / 联系人 / 来源 / 预估金额 / 状态 / 操作。
        <span class="badge text-bg-danger">流失原因</span> 仅在筛选为<strong>“已流失”</strong>时
        才作为额外一列展示（其余标签页下该列隐藏，避免整列“—”）。</p>

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
    <p class="small text-muted">线索的流失原因在“已流失”标签页的<strong>流失原因</strong>列查阅；重新激活会清空该原因与流失时间。</p>
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
            <tr><td><span class="badge text-bg-success">成交</span></td><td>成功签约，<strong>自动生成订单</strong>，商机保留在成交列</td></tr>
            <tr><td><span class="badge text-bg-danger">丢单</span></td><td>未能成交，<strong>自动归档</strong>并移出看板</td></tr>
        </tbody>
    </table>

    <p class="text-muted small mb-3">看板仅保留 进行中 / 方案阶段 / 谈判中 / 成交 四列；丢单商机进入"已归档"，可在归档页"恢复"后回到"进行中"列继续跟进。</p>

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

<!-- 设置 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-gear-fill me-2"></i>设置</h5>
    <p>入口：左侧菜单<strong>设置</strong>，或右上角账户下拉菜单（个人信息 / 修改密码 / 应用信息）。</p>

    <div class="table-responsive">
        <table class="table table-sm mb-3">
            <thead><tr><th>选项卡</th><th>谁可改</th><th>内容</th><th>生效范围</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>个人信息</strong></td>
                    <td>本人</td>
                    <td>姓名、邮箱（登录账号）、职位、电话、WhatsApp、备注</td>
                    <td>全局：客户 / 线索 / 商机 / 订单的<strong>负责人</strong>、跟进与动态的记录人、附件上传人、顶栏用户名</td>
                </tr>
                <tr>
                    <td><strong>修改密码</strong></td>
                    <td>本人</td>
                    <td>当前密码 + 新密码（至少 6 位，需两次一致）</td>
                    <td>下次登录</td>
                </tr>
                <tr>
                    <td><strong>应用信息</strong></td>
                    <td><span class="badge text-bg-danger">仅管理员</span></td>
                    <td>系统名称、系统副标题、公司名称、版权信息、货币符号</td>
                    <td>全站：侧边栏、浏览器标题、登录页、所有金额显示</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h6 class="text-muted">为什么改个名字，客户上的负责人也跟着变了？</h6>
    <p>这是有意设计的：<strong>人的信息只存一份</strong>。客户、线索、商机、订单只记录“这是哪个用户”
        （<code>owner_id</code>），姓名与联系方式在读取时从账号表取，因此不存在“改了账号、但客户还显示旧名字”的不一致，
        也不需额外的同步按钮。</p>
    <p class="mb-0">“个人信息”页右侧的<strong>信息同步范围</strong>会实时列出你的账号当前被多少条记录引用，
        保存后这些位置会直接显示新内容。</p>
</div>

<!-- AI 助手 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-robot me-2"></i>AI 助手（可选）</h5>
    <p>把<strong>邮件、WhatsApp 对话、会议记录</strong>等原文粘到 <strong>AI 助手</strong> 页，它会整理成线索、更新状态、补跟进记录等——但<strong>不会直接改库</strong>：它先给出一份“操作计划”，你看清楚再点“确认执行”。默认状态是<strong>关闭</strong>，需要管理员在 <strong>设置 → AI 助手</strong> 里选服务商、填模型与 API Key。</p>

    <div class="table-responsive">
        <table class="table table-sm mb-3">
            <thead><tr><th>AI 能做</th><th>AI 不能做</th></tr></thead>
            <tbody><tr>
                <td>新建线索 / 更新线索状态（含流失原因）/ 新建客户 / 修改客户资料 / 添加跟进记录 / 新建商机 / 推进商机阶段</td>
                <td>删除任何数据；调用上面之外的操作；填写不存在的 ID、非法邮箱或离谱金额；操作同事负责的记录</td>
            </tr></tbody>
        </table>
    </div>

    <h6 class="text-muted">两种执行方式</h6>
    <ul>
        <li><strong>预览确认（默认）</strong> — 先列出计划表格（做什么、参数、理由、校验结果），你点“确认执行”才写库。</li>
        <li class="mb-0"><strong>自动执行</strong> — 校验通过就直接写库，适合已信任的场景；两种都会留操作记录。</li>
    </ul>

    <p class="small text-muted mb-0">
        每次请求都写入 <code>ai_actions</code>（谁发起、原始指令、计划、执行结果、服务商/模型/耗时），在“操作记录”页可查。若担心资料外泄，选<strong>本地 Ollama</strong> 或<strong>内置演示模型</strong>（完全离线不联网）。
    </p>
</div>


<!-- 数据库迁移 -->
<div class="card card-table p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-database me-2"></i>数据库说明</h5>
    <p>系统使用 SQLite 数据库，数据库文件位于 <code>database/crm.sqlite</code>（已被 git 忽略）。</p>

    <h6 class="text-muted">创建 / 升级 / 修复数据库（推荐）</h6>
    <p>在项目根目录执行统一迁移入口，幂等、可重复运行，会自动补齐缺失的表与种子数据：</p>
    <pre class="bg-light p-3 rounded small mb-0"><code>php database/migrate.php</code></pre>
    <p class="mt-3 mb-0 text-muted small">增量迁移（<code>database/migrations/NNN_*.sql</code>）若为“纯加列”且基线
        <code>schema.sql</code> 已含该列，脚本会输出 <code>skipped: …</code> 并直接登记，
        不会报 duplicate column name；旧数据库缺列时仍会正常执行。</p>
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
                    仅指<strong>全新数据库</strong>的种子账号（写在 <code>database/schema.sql</code> 的种子里）：<br>
                    邮箱：<code>admin@example.com</code> · 密码：<code>password</code><br>
                    一旦有人改过姓名/邮箱/密码（设置 → 个人信息 / 修改密码），以实际账号为准；
                    已部署的实例若仍是默认口令，请立刻修改后再对外访问。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                    同事改名/换手机号了，需要逐个改客户的负责人吗？
                </button>
            </h2>
            <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    不需要。让他自己在<strong>设置 → 个人信息</strong>里修改，保存后客户 / 线索 / 商机 / 订单的
                    负责人、跟进记录的操作人都会<strong>立即显示新信息</strong>，
                    因为业务记录只存用户 ID，姓名是读数据时从 <code>users</code> 表实时取回的。<br>
                    注意区分：如果是“换一个人负责”，要去客户 / 订单页把负责人改成另一个账号，而不是改个人信息。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
                    AI 助手会不会乱改我的数据？
                </button>
            </h2>
            <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    不会“乱改”。它只能返回白名单里的 <strong>17 种操作</strong>（查询 2 、写入 10、删除 5），参数由服务端二次校验（枚举值、邮箱、金额上限、
                    ID 是否真实存在、<strong>这条记录是不是你负责的</strong>），任何一项不过就整条拒绝并告诉你原因。<br>
                    默认还是<strong>预览确认</strong>模式：不点“确认执行”就不写库；点确认时还会再校验一遍，
                    防止你看计划的这几分钟里数据已经变了。<br>
                    它能查数据、能改、<strong>也能删</strong>（见下一条），但改不了应用设置与别人负责的行；全过程写入 <code>ai_actions</code>。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqA">
                    怎么让 AI（或新同事）快速摸清这个项目？
                </button>
            </h2>
            <div id="faqA" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    本页下方的<strong>“技术参考”</strong>不是手写的：路由、数据表、枚举、设置项、AI 工具与服务商预设都由
                    <code>app/core/AppMap.php</code> 从运行中的代码与数据库读出来，所以不会和实现脱节。<br>
                    想要纯文本版（贴给模型最省事）：访问 <a href="<?= url('/help/context') ?>" target="_blank"><code>/help/context</code></a>，
                    或 <code>curl -s http://你的地址/help/context</code>（需登录会话）。<br>
                    读代码顺序建议：<code>app/routes.php</code> → 对应 <code>app/controllers/*</code> → <code>app/models/*</code> → <code>app/views/*</code>，
                    基础设施只有 <code>app/core/</code> 里的 7 个类。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqB">
                    为什么列表里的“创建时间”比实际早 8 小时？
                </button>
            </h2>
            <div id="faqB" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    已知不一致：<code>created_at</code> / <code>updated_at</code> 由 SQLite 默认值和触发器写入，那是 <strong>UTC</strong>；
                    而 PHP 侧写的 <code>lost_at</code>、<code>conversion_time</code>、<code>archived_at</code>、<code>stage_*_at</code>、
                    <code>users.updated_at</code> 用的是 <code>Asia/Shanghai</code>（<code>app/bootstrap.php</code> 里设的），两者差 8 小时。
                    页面按字面量显示，不做时区换算，所以看起来“早了 8 小时”。要统一建议全存 UTC 并在显示层换算（属于行为变更，需要单独定）。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqC">
                    AI 测试连接报“无法发起 https 请求”
                </button>
            </h2>
            <div id="faqC" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    本项目不用 curl 扩展，走 PHP 流，而 https 需要 <code>openssl</code> 扩展。精简版 PHP（尤其 Windows）常默认不开：
                    在 <code>php.ini</code> 里把 <code>;extension=openssl</code> 的分号去掉，<strong>重启 PHP / Web 服务</strong>（只刷新页面不生效），
                    本页“运行环境”一栏会立刻变成 HTTPS：可用。期间仍可用<strong>内置演示模型</strong>或<strong>本地 Ollama</strong>（http 不需要 openssl）。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqD">
                    忘记密码 / 需要把某个账号的权限收回怎么办？
                </button>
            </h2>
            <div id="faqD" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    界面上没有“管理员重置他人密码”的入口（成员管理页尚未实现）。可在服务器上执行一条命令重置：<br>
                    <code>php -r "$d=new PDO('sqlite:database/crm.sqlite');$d->prepare('UPDATE users SET password=? WHERE email=?')->execute([password_hash('新密码', PASSWORD_DEFAULT), 'someone@example.com']);"</code><br>
                    口令规则：至少 6 位；表里只存 bcrypt 散列，任何页面都不会回显密码；连续 5 次登录失败会锁该会话 60 秒（换浏览器即重置）。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqE">
                    为什么销售账号也能看到/编辑别人的客户？
                </button>
            </h2>
            <div id="faqE" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    现状如此：业务控制器只要求登录，<strong>负责人只是归属标签，不是权限边界</strong>；
                    <code>canManageResource()</code> / <code>authorizeResource()</code> 已在 <code>app/core/helpers.php</code>、<code>Controller</code> 里备好但目前没有被业务控制器调用。
                    目前只有 <strong>AI 助手</strong>强制这条规则（越权计划会被拒绝）。要把规则铺到界面上，需要在各 <code>update/destroy</code> 前加 <code>authorizeResource()</code> ——这是行为变更，先确认团队接受度。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqF">
                    “恢复默认”会不会把别的页签设置或 API Key 一起清掉？
                </button>
            </h2>
            <div id="faqF" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    不会。重置按页签分组（<code>Setting::keysInGroup()</code>）：应用信息只管 <code>app_*</code> / <code>currency_symbol</code> / <code>copyright_notice</code>，
                    AI 只管 <code>ai_*</code>，并且<strong>任何重置都不会清除密钥</strong>——密钥只能通过“清除已保存的 API Key”那个勾选框显式删除。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqI">
                    AI 现在能删数据了，怎么防误删？
                </button>
            </h2>
            <div id="faqI" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    删除工具（<code>delete_lead</code> / <code>delete_deal</code> / <code>delete_order</code> /
                    <code>delete_customer</code> / <code>delete_ai_request</code>）有六道固定门槛：<br>
                    ① 参数里必须写 <code>confirm: true</code> 与一句话 <code>reason</code>，缺一个就整条被拒；<br>
                    ② 就算开了“自动执行”，<strong>删除也永远不会自动执行</strong>，必须你本人点“确认执行”（页面会橙色提醒）；<br>
                    ③ 预览表里直接显示<strong>连带影响</strong>：删客户会列出名下多少线索/商机/订单/附件，不必事后才发现；<br>
                    ④ 归属检查：<code>Ai::validatePlan()</code> 走 <code>canManageResource()</code>，销售动不了别人负责的行；<br>
                    ⑤ 执行后被删内容以<strong>快照</strong>写进 <code>ai_actions.result_json</code>，能查回当时删了什么；<br>
                    ⑥ 总开关：设置 → AI 助手 → <strong>允许 AI 删除数据</strong>（<code>ai_allow_delete</code>，也可用环境变量
                    <code>AI_ALLOW_DELETE=0</code> 强制关掉），关掉后 AI 只能查/增/改。<br>
                    查询（<code>search_records</code> / <code>get_record</code>）不写库，但<strong>只能查白名单表</strong>：
                    <code>app_settings</code> 与 <code>users</code> 不在清单里，所以 API Key、密码散列它拿不到。
                    另外提醒一句：删除就是删除，数据库没有回收站——<code>database/crm.sqlite</code> 定时备份仍是唯一的后悔药。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqG">
                    提交表单提示“表单提交无效（CSRF 验证失败）”
                </button>
            </h2>
            <div id="faqG" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    每个写操作都带 <code>csrf_token</code> 并与 Session 比对（<code>Controller::verifyCsrf()</code>，失败返回 HTTP 419）。
                    常见原因：页面在登录前就打开着、Session 已过期、或 Cookie 被浏览器/代理丢掉。刷新页面重新登录即可；
                    这不是数据问题——写操作在校验通过前根本不会执行。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqK">
                    怎么叫 AI 按条件批量处理？（例如“删掉印度所有客户”）
                </button>
            </h2>
            <div id="faqK" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    直接说条件就行，<strong>不需要你先查编号</strong>。AI 助手现在会先查后做：
                    第一轮它只发查询，系统当场执行（只读不写库），把命中的真实编号回灌给它，第二轮它才出写/删计划
                    （最多 <code>3</code> 轮，防止无限自问自答）。<br>
                    可用的条件：<code>country</code>（中英都认：印度 / India、美国 / USA）、<code>status</code>、
                    <code>stage</code>、<code>owner</code>（填负责人姓名），再加关键词 <code>q</code>。<br>
                    能用的句子比如：「现在有多少客户了」（直接回答准确总数）、「删除印度国家的所有客户，连同他们的线索、商机和订单」、
                    「删除客户名字为 armtek 的所有信息」、「把 open 阶段的商机都标为已失效」、「删除所有AI请求」（清自己的审计历史）。<br>
                    没有条件又没说“所有”的查询会被拒（免得一句含糊的话扫全表）；要整表请说“所有/全部”，系统会显式按整表处理。<br>
                    <strong>还有一条服务端硬护栏</strong>：一句指令里出现 ≥2 个删除动作时，本轮必须真的查过库（或你自己点名了编号），否则系统会把它推回去先查。这不是假想风险：实测模型会凭名字猜国籍，把伊拉克、埃及的客户也算成“印度客户”。<br>
                    预览里每条删除都会显示该记录的<strong>关键事实</strong>（国家 / 状态 / 阶段 / 收款 / 公司）与连带数量，点“确认执行”前请用它核对一眼——确认按钮就是最后一道闸。
                    顺便一句：「把我的名字改成 XX」这类<strong>不归 AI 管</strong> —— 请到 设置 → 个人信息 改（姓名会同步成整站“负责人”标签，不该由一段对话改动）。
                    删客户只需一个 <code>delete_customer</code>：它本身就会连带删掉该客户名下的线索/商机/订单
                    （提示词也专门教了模型不要重复发子记录删除）。<br>
                    安全边界依旧：预览会显示<strong>合计</strong>（“将删除 2 条记录，连带线索 2、商机 2、订单 2，约 8 行”）
                    与每条的「将删除」，删除永远要你手动“确认执行”（自动执行模式也不例外），
                    一个计划最多删 <code>20</code> 条，超出会被整批拒绝并让你分批（一句“删掉所有客户”清不了库）。<br>
                    小提醒：<strong>国家写法中英互认</strong> —— 你库里同一列是混着写的（印度 / Egypt / United States…），
                    说「印度」或 India 都能命中；匹配是<strong>等值</strong>不是包含，所以「印度」不会连带命中「印度尼西亚」。<br>
                    拿不准时先说「查一下国家是印度的客户」，确认列表对了再下删除指令。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqL">
                    AI 到底能改哪些字段？（以前说“线索没有来源国家字段”）
                </button>
            </h2>
            <div id="faqL" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    <strong>表里有的列，AI 就能改</strong>。这一版的参数清单不再手写：
                    <code>Ai::fieldsFor()</code> 直接读数据库表结构生成，提示词、参数校验、真正落库三处共用同一份，
                    所以“提示词里说能改”与“服务器允许改”不会再分叉，将来加一列也自动可写。<br>
                    现在的覆盖：线索 <strong>22</strong> 项（含来源国家、来源城市、Facebook / TikTok / 微信、网站、线索时间、
                    是否首次从中国采购、是否有进口能力、地址、负责人等）、客户 <strong>19</strong> 项、订单 <strong>11</strong> 项、
                    商机 <strong>7</strong> 项、跟进记录 <strong>6</strong> 项；另外能改系统设置（仅管理员，见下）。<br>
                    历史原因说实话不体面：上一版我给每个工具手写参数表，<code>update_lead</code> 漏了
                    <code>source_country</code>，于是它回你“线索没有来源国家字段”——字段一直在，是我的清单漏了。
                    现在这条由 <code>AiFieldsTest</code> 钉住：<em>每张表的每一列要么可写、要么被明确排除，缺一个测试就失败</em>。<br>
                    三点用法：
                    <ol class="mb-2">
                        <li><strong>只说你要改的那几项</strong>，其余字段一个都不动；回执会用中文名告诉你改了哪些（“已更新：来源国家”）。</li>
                        <li><strong>想清空就明说</strong>（“把备注清空”），可空列会被真的写成空；标题、名称这类必填列会拒绝清空而不是写坏数据。</li>
                        <li><strong>负责人可以写姓名</strong>（“这条线索转给沈万明”）：管理员能指派任何人，普通账号只能指给自己。</li>
                    </ol>
                    写不了的只有这几类（<code>Ai::PROTECTED_COLUMNS</code>，由系统维护）：编号 <code>public_code</code>
                    与单号 <code>order_number</code>（改了你去哪儿复制的引用就会指向别的记录）、
                    <code>created_at / updated_at</code>、以及 <code>lost_at / archived_at / stage_*_at</code> 这些时间戳——
                    它们是“发生过某事”的结果：你说“标为流失”，系统会同时写 <code>status</code>、<code>lost_reason</code> 和 <code>lost_at</code>，
                    和你在页面上点出来的一模一样。<br>
                    新增的几项能力：<code>update_follow_up</code>（跟进记录以前只能加不能改）、
                    <code>set_order_items</code>（整单替换订单明细，<strong>金额与 subtotal 由系统按数量×单价重算</strong>，模型传的不作数）、
                    <code>get_settings</code> + <code>update_setting</code>（改应用名称 / 副标题 / 公司名 / 货币符号 / AI 开关与参数，
                    <strong>仅管理员</strong>、一次一项、走和设置页同一套校验；<strong>API 密钥永远不允许经 AI 修改</strong>，
                    读取时也只显示“已配置，不回显”）。<br>
                    账号本身的资料（你的姓名、邮箱、密码）仍不在 AI 权限内 —— 请到 设置 → 个人信息 修改。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqJ">
                    AI 回复里的 <code>LEAD-000002</code>、<code>CUS-000007</code> 是什么？
                </button>
            </h2>
            <div id="faqJ" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    那是记录的<strong>稳定编号</strong>（<code>public_code</code> 列）：客户 <code>CUS-</code>、线索 <code>LEAD-</code>、商机 <code>DEAL-</code> 各自前缀 + 六位数字，
                    数字部分就是这行的 <code>id</code>，由 <code>Model::publicCode()</code> 在新增时自动写入 —— 不能手工指定，也不出现在任何 <code>update_*</code> 工具的参数里，所以谁都改不了它。
                    订单不用它，沿用原有的 <code>order_number</code>（<code>ORD-2026-007</code>）。<br>
                    为什么要多这一列：列表里“3 号客户”和“3 号线索”会混，编号自带类型；也比裸 id 好念好抄。
                    现在客户列表、线索列表、商机看板卡片和客户详情页上都看得到。<br>
                    跟 AI 说话时<strong>直接报编号最稳</strong>：「把 LEAD-000002 标记为联系不上」「删掉客户 CUS-000007」。
                    <code>search_records</code> / <code>get_record</code> 返回的第一项就是编号；
                    编一个不存在的编号会被服务端拒掉并提示先搜，绝不会误伤别的记录。
                    迁移前留下的空编号由 <code>Model::codeOf()</code> 按同一规则推导显示（迁移 007 也会一次性回填）。
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqH">
                    AI 助手很慢，甚至弹“Maximum execution time …”？
                </button>
            </h2>
            <div id="faqH" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    模型调用是同步等待的，慢的常见来源按本机实测见效速度排序：<br>
                    ⓪ <strong>快速模式</strong>（<code>ai_fast_mode</code>，默认开）——思考型模型会先写一大段推理，实测同一条“新建线索”指令：关 = 7.8 秒且回 0 个动作，开 = 1.3 秒且给出正确的 <code>create_lead</code>；接口不支持该参数时会自动退回默认方式。<br>
                    ① <strong>最大回复长度</strong>（<code>ai_max_tokens</code>，默认 800）——输出越长等得越久，选“400 tokens”通常立刻快一半；<br>
                    ② <strong>模型档位</strong>——换 flash 档（<code>deepseek-v4-flash</code> / <code>qwen3.8-flash</code> / <code>gpt-4o-mini</code>），推理型/思考型模型做一次计划可能要几十秒；<br>
                    ③ <strong>响应超时</strong>（<code>ai_timeout</code>，默认 45 秒）。<br>
                    现在这三项都在 <strong>设置 → AI 助手</strong> 里可改，<code>/ai</code> 页面顶部会显示本次的“超时 Xs / ≤N tokens”预算。
                    超时会给出可读提示（“45 秒内没有收到 AI 响应…调大响应超时/换更快模型”），
                    而不再把页面打死：请求内部会把 PHP 的 <code>max_execution_time</code>（php -S / Apache 默认 30 秒）抬到超时之上，
                    并让网络层先一步放弃，所以不会再出现 <code>Fatal error: Maximum execution time …</code>。
                    另：开发用的 <code>php -S</code> 是单进程的，AI 等待期间其它请求会排队。
                    Linux/macOS 可以 <code>PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:3500 -t public</code> 多开 worker；
                    <strong>Windows 下该变量无效</strong>（<code>php -S</code> 不能 fork），要么接受这段排队，要么用 nginx + php-cgi 跑开发环境。
                </div>
            </div>
        </div>
    </div>
</div>
