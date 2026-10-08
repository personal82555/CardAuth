<template>
<div class="docs-app">
  <div class="bg-canvas">
    <div class="bg-grid"></div>
    <div class="bg-orb orb-a"></div>
    <div class="bg-orb orb-b"></div>
    <div class="bg-orb orb-c"></div>
  </div>

  <header class="top-bar">
    <div class="top-brand">
      <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
      <div class="top-brand-text">
        <span class="top-name">CardAuth</span>
        <span class="top-sub">卡密授权管理系统</span>
      </div>
    </div>
    <div class="top-actions">
      <router-link to="/shop" class="top-btn btn-primary">进入购买中心</router-link>
      <router-link to="/login" class="top-btn btn-ghost">管理登录</router-link>
    </div>
  </header>

  <main class="docs-wrap">
    <div class="docs-hero">
      <h1 class="hero-title">CardAuth 授权管理系统</h1>
      <p class="hero-desc">轻量级<b>卡密（激活码）+ 机器授权</b>解决方案 · 多项目管理 · 代理分销 · 在线支付 · API 对接</p>
    </div>

    <div class="docs-grid">
      <div class="docs-card">
        <div class="docs-card-head"><span>系统介绍</span></div>
        <p>CardAuth 是一套轻量级的<b>卡密（激活码）+ 机器授权管理系统</b>，提供多项目管理、卡密生成与分销、代理体系、在线支付购买及授权验证 API。适用于软件授权、机器人授权等场景。</p>
        <ul>
          <li><b>多项目</b>：每个项目拥有独立商品、API Key 与卡密池</li>
          <li><b>卡密</b>：支持批量生成、按天/月/年设定期限、导入导出</li>
          <li><b>代理/分销</b>：代理充值额度、以折扣价采购并分发卡密</li>
          <li><b>在线支付</b>：内置易支付对接，用户可直接付款购买</li>
          <li><b>授权验证</b>：REST API 实时校验卡密与机器码绑定状态</li>
          <li><b>安装统计</b>：记录安装次数、存活/已删除、最后上线时间</li>
          <li><b>版本推送</b>：上传安装包并推送客户端下载，支持强制更新提示</li>
          <li><b>自动发卡</b>：App/IPTV 支付成功后从未用卡池自动取卡并绑定机器码</li>
        </ul>
      </div>

      <div class="docs-card">
        <div class="docs-card-head"><span>购买与激活流程</span></div>
        <ol>
          <li><b>购买授权</b>：在<b>购买中心</b>选择项目与商品，支付宝/微信付款后系统即时发放卡密</li>
          <li><b>在线授权</b>：在购买中心「在线授权」中输入卡密与机器码，完成绑定</li>
          <li><b>授权查询</b>：按卡密或机器码查询绑定状态与到期时间</li>
          <li><b>续期</b>：对已绑定的机器人再次使用卡密授权，自动延长有效期</li>
        </ol>
        <p class="docs-tip">卡密未使用前不限期有效；一旦绑定，有效期自激活时开始计算。请妥善保管卡密，谨防泄露。</p>
      </div>

      <div class="docs-card">
        <div class="docs-card-head"><span>自动发卡（App/IPTV 直购）</span></div>
        <p>支付成功后系统自动完成发卡，适合电视端 / App 内直接购买、无需人工发卡的场景。</p>
        <ol>
          <li>客户端下单时，在 <code>contact_info</code> 中带上机器码：<br><code>MACHINE:TV-XXXXXX</code></li>
          <li>支付成功（回调 / 同步完成 / 管理端补单）触发自动发卡</li>
          <li>系统从同项目、同套餐的<b>未用卡池</b>取出 1 张卡</li>
          <li>自动绑定机器码（<code>status=used</code>），订单关联该卡密</li>
          <li>App 用订单号调用 <code>/api/public/orders/query</code> 即可拿到卡密与到期信息</li>
        </ol>
        <div class="docs-code">
          <div class="docs-code-title">contact_info 约定</div>
          <pre>MACHINE:TV-123456
// 未填写 MACHINE 时，系统使用 AUTO-订单号 占位并仍完成绑定</pre>
        </div>
        <ul>
          <li>订单若填写了 <code>bot_qq</code> + <code>contact_qq</code>，则走 QQ 授权续费/新建，不会自动发卡</li>
          <li>卡池无未用卡时：订单仍标记已支付，管理端需补卡后手动处理</li>
          <li>发卡结果：卡密 <code>bind_info.device_info=auto-delivery</code>，并记录 <code>order_no</code></li>
        </ul>
      </div>

      <div class="docs-card">
        <div class="docs-card-head"><span>安装统计与版本推送</span></div>
        <p>系统支持统计项目被安装次数、区分存活/已删除、记录最后上线时间，并可向客户端推送最新安装包（支持强制更新提示）。</p>
        <ul>
          <li><b>安装统计</b>：每次安装独立计数（含已删除）；同机器删除后重装会再次计入</li>
          <li><b>存活 / 已删除</b>：后台可软删除安装记录，也可恢复；统计口径含已删除</li>
          <li><b>最后上线</b>：客户端 verify / check-update / heartbeat 成功时自动更新</li>
          <li><b>版本推送</b>：上传安装包后，客户端检查更新即可获得下载地址与更新说明</li>
          <li><b>强制推送</b>：开启后返回强制更新提示（仅提示下载，不阻断授权）</li>
        </ul>
        <h4 class="docs-sub">后台使用方法</h4>
        <ol>
          <li>进入管理后台 → <b>业务管理 → 安装统计</b>：查看总安装、存活、已删除、最后上线、分项目明细</li>
          <li>进入 <b>业务管理 → 版本推送</b>（或项目管理行内「版本管理」）</li>
          <li>选择项目 → <b>发布新版本</b>：填写版本号、上传安装包、更新说明</li>
          <li>可选：<b>强制推送</b>（客户端弹强制更新）、<b>最低强制版本</b>（低于此版本强制更新）</li>
          <li>发布后客户端调用 check-update 即可收到下载提示；可随时「设为最新」「取消强制」「删除版本」</li>
        </ol>
        <p class="doc-note">权限：超级管理员/项目管理员可发布与修改版本；代理仅可查看安装统计与版本列表，不可发布。</p>
        <p class="docs-tip">强制推送仅影响客户端更新提示，不会让旧版本卡密验证失败。若需彻底停用旧版本，请在卡密/授权侧处理。</p>
      </div>

      <div class="docs-card docs-card-wide">
        <div class="docs-card-head"><span>开发者对接（API）</span></div>

        <h4 class="docs-sub">1. 鉴权方式</h4>
        <div class="docs-table-wrap">
          <table class="docs-table">
            <thead><tr><th>类型</th><th>说明</th></tr></thead>
            <tbody>
              <tr><td>公开接口</td><td>无需认证：商品浏览、订单创建/查询、QQ授权查询与验证</td></tr>
              <tr><td>API Key</td><td>项目密钥，用于机器码授权验证接口，请求头 <code>X-Api-Key</code>（管理员在「项目管理」中获取）</td></tr>
              <tr><td>JWT Token</td><td>后台/代理站点使用，请求头 <code>Authorization: Bearer &lt;token&gt;</code>，有效期 24 小时</td></tr>
            </tbody>
          </table>
        </div>
        <p class="doc-note">所有接口需携带 <code>Content-Type: application/json</code>；统一响应结构 <code>{ "code": 200, "message": "操作成功", "data": {...} }</code>。</p>

        <h4 class="docs-sub">2. 机器码授权验证（核心接口）</h4>
        <div class="docs-code">
          <div class="docs-code-title">POST /api/public/verify　<span>Header: X-Api-Key: 项目APIKey</span></div>
          <pre>{
  "card_key":       "CA-XXXX-XXXX",     // 必填：卡密
  "machine_id":     "UNIQUE-DEVICE-FP", // 必填：机器指纹（首次调用即绑定）
  "ip":             "客户端IP",         // 选填：默认取请求来源IP
  "device_info":    "设备描述信息",      // 选填：留作记录
  "client_version": "1.0.0"             // 选填：客户端版本，用于更新提示
}</pre>
        </div>
        <div class="docs-table-wrap">
          <table class="docs-table">
            <thead><tr><th>场景</th><th>响应 data</th></tr></thead>
            <tbody>
              <tr><td>首次激活（卡密未使用）</td><td><code>valid:true, message:"激活成功", type, duration_days, expire_time, is_permanent</code>，绑定机器指纹并开始计时；同时登记安装记录并附带更新字段</td></tr>
              <tr><td>已激活 · 同一机器</td><td><code>valid:true, message:"授权有效", expire_time, bound_at, remaining_days</code>；更新安装 last_online_at 并附带更新字段</td></tr>
              <tr><td>已激活 · 换机器</td><td><code>valid:false, message:"设备不匹配，请使用绑定的设备"</code></td></tr>
              <tr><td>授权已过期</td><td><code>valid:false, message:"授权已过期"</code></td></tr>
              <tr><td>卡密不存在 / 已禁用</td><td><code>valid:false, message:"卡密不存在" / "卡密已被禁用"</code></td></tr>
            </tbody>
          </table>
        </div>
        <p class="doc-note"><b>注：</b><code>duration_days=0</code> 表示永久卡，<code>expire_time</code> 为空且 <code>is_permanent=true</code>。verify 成功时还会附带更新提示字段（见下节），便于客户端弹窗下载最新安装包。</p>

        <h4 class="docs-sub">3. 安装上报 / 检查更新（客户端）</h4>
        <div class="docs-code">
          <div class="docs-code-title">POST /api/public/installations/check-update　<span>Header: X-Api-Key: 项目APIKey</span></div>
          <pre>{
  "machine_id":     "UNIQUE-DEVICE-FP", // 必填：机器指纹
  "client_version": "1.0.0",            // 选填：当前版本，用于对比是否需要更新
  "card_key":       "CA-XXXX-XXXX",     // 选填：关联卡密，便于统计归属
  "device_info":    "Windows 11"        // 选填
}</pre>
        </div>
        <div class="docs-code">
          <div class="docs-code-title">响应示例</div>
          <pre>{
  "code": 200,
  "data": {
    "update_available": true,       // 是否有新版本
    "update_required": true,        // 是否强制更新（仅提示，不阻断授权）
    "current_version": "1.0.0",
    "latest_version": "1.2.0",
    "version": "1.2.0",
    "download_url": "/uploads/packages/1/pkg_xxx.zip",
    "file_size": 10485760,
    "checksum": "sha256...",
    "changelog": "本次更新说明",
    "min_client_version": "1.1.0",  // 低于此版本强制更新
    "is_force": 1,
    "install_id": 123
  }
}</pre>
        </div>
        <div class="docs-code">
          <div class="docs-code-title">POST /api/public/installations/heartbeat　<span>仅更新在线时间</span></div>
          <pre>{
  "machine_id":     "UNIQUE-DEVICE-FP",
  "client_version": "1.0.0",
  "card_key":       "CA-XXXX-XXXX"    // 选填
}
响应 data: { "install_id": 123 }</pre>
        </div>
        <p><b>客户端接入建议：</b></p>
        <ul>
          <li>启动/周期心跳时调用 <code>check-update</code>，同时完成安装登记与在线上报</li>
          <li><code>update_available=true</code> 且 <code>update_required=false</code> → 弹出「发现新版本，是否下载？」</li>
          <li><code>update_required=true</code> → 强制更新提示（不可关闭），引导下载 <code>download_url</code></li>
          <li>授权 <code>verify</code> 成功时也会返回相同更新字段，并自动刷新 last_online_at</li>
        </ul>

        <h4 class="docs-sub">4. 后台安装/版本管理接口（JWT）</h4>
        <div class="docs-table-wrap">
          <table class="docs-table">
            <thead><tr><th>方法</th><th>路径</th><th>说明</th></tr></thead>
            <tbody>
              <tr><td>GET</td><td><code>/api/installations</code></td><td>安装列表（可筛项目/状态/关键字/日期）</td></tr>
              <tr><td>GET</td><td><code>/api/installations/stats</code></td><td>统计：总安装（含已删除）、存活、已删除、今日新增、最后上线、分项目</td></tr>
              <tr><td>DELETE</td><td><code>/api/installations/{id}</code></td><td>标记已删除（软删除）</td></tr>
              <tr><td>POST</td><td><code>/api/installations/{id}/restore</code></td><td>恢复为存活</td></tr>
              <tr><td>GET</td><td><code>/api/projects/{projectId}/versions</code></td><td>版本列表（代理只读）</td></tr>
              <tr><td>POST</td><td><code>/api/projects/{projectId}/versions</code></td><td>上传发布版本（multipart）</td></tr>
              <tr><td>PUT</td><td><code>/api/versions/{id}/force</code></td><td>强制推送开关</td></tr>
              <tr><td>PUT</td><td><code>/api/versions/{id}/latest</code></td><td>设为最新版本</td></tr>
            </tbody>
          </table>
        </div>
        <p class="doc-note">发布版本需 <code>multipart/form-data</code>：字段 <code>version</code>、<code>file</code>、可选 <code>changelog</code>、<code>is_force</code>、<code>min_client_version</code>、<code>is_latest</code>。支持 zip/tar/gz/7z/exe/dmg/apk/msi/pkg/rar/bin，单文件最大 200MB。</p>

        <h4 class="docs-sub">5. QQ 授权验证（机器人场景）</h4>
        <div class="docs-code">
          <div class="docs-code-title">POST /api/public/authorizations/verify</div>
          <pre>{
  "bot_qq":     "123456789",        // 必填：机器码
  "card_key":   "CA-XXXX-XXXX",     // 选填：限定卡密校验
  "contact_qq": "987654321"         // 选填：联系人QQ
}</pre>
        </div>
        <p>响应 <code>data</code>：<code>valid</code>、<code>has_valid_auth</code>、<code>bot_qq</code>、<code>contact_qq</code>、<code>card_key</code>、<code>project_name</code>、<code>expire_time</code>、<code>days_left</code>、<code>message</code>。</p>

        <h4 class="docs-sub">6. QQ 授权状态查询</h4>
        <div class="docs-code">
          <div class="docs-code-title">GET /api/public/authorizations/query?bot_qq=123456789</div>
          <pre>{
  "code": 200,
  "data": {
    "bot_qq": "123456789",
    "total": 2, "active_count": 1, "expired_count": 1, "revoked_count": 0,
    "has_valid_auth": true,
    "list": [
      { "card_key": "CA-XXXX-XXXX", "project_name": "演示项目",
        "duration_days": 30, "status": "active",
        "authorized_at": "2026-01-01 12:00:00",
        "expire_time": "2026-01-31 12:00:00", "is_expired": false }
    ]
  }
}</pre>
        </div>

        <h4 class="docs-sub">7. 商城公共接口</h4>
        <div class="docs-code">
          <div class="docs-code-title">GET /api/public/projects — 项目与商品列表</div>
          <pre>GET /api/public/projects/{project_id}/card-types — 项目套餐

POST /api/public/orders — 创建订单（在服务器内支付，返回支付链接）
{
  "project_id": 1, "card_type_id": 1,
  "amount": 29.90, "pay_type": "wxpay",    // wxpay | alipay | qqpay
  "contact_qq": "987654321", "bot_qq": "123456789",  // QQ授权场景选填
  "contact_info": "MACHINE:TV-123456",     // App自动发卡：机器码
  "coupon_code": ""                        // 选填：优惠码
}
响应 data: { "order_no":"2026062317530410859", "pay_url":"https://…", "is_renew":false }

GET /api/public/orders/query?order_no=2026062317530410859 — 订单查询（已自动发卡时含 card_key / expire_time）
GET /api/public/cards/query?card_key=CA-XXXX — 卡密查询</pre>
        </div>
        <p class="doc-note">App 下单时在 <code>contact_info</code> 写 <code>MACHINE:TV-XXXX</code>，支付成功后自动从未用卡池取卡并绑定机器码（详见上方「自动发卡」）。</p>

        <h4 class="docs-sub">8. 调用示例（CURL）</h4>
        <div class="docs-code">
          <div class="docs-code-title">curl · 授权验证</div>
          <pre>curl -X POST https://你的域名/api/public/verify \
     -H "X-Api-Key: 你的项目APIKey" \
     -H "Content-Type: application/json" \
     -d '{"card_key":"CA-XXXX-XXXX","machine_id":"FP-001","client_version":"1.0.0"}'</pre>
        </div>
        <div class="docs-code">
          <div class="docs-code-title">curl · 检查更新 / 心跳</div>
          <pre>curl -X POST https://你的域名/api/public/installations/check-update \
     -H "X-Api-Key: 你的项目APIKey" \
     -H "Content-Type: application/json" \
     -d '{"machine_id":"FP-001","client_version":"1.0.0"}'

curl -X POST https://你的域名/api/public/installations/heartbeat \
     -H "X-Api-Key: 你的项目APIKey" \
     -H "Content-Type: application/json" \
     -d '{"machine_id":"FP-001","client_version":"1.0.0"}'</pre>
        </div>

        <h4 class="docs-sub">9. 频率限制</h4>
        <p>公开接口默认限流：QQ 授权验证等公共接口每 <b>60 秒 30 次</b>；机器码授权验证与安装上报每 <b>60 秒 60 次</b>。超出后返回 <code>429</code>，请控制调用频率并缓存验证结果（建议客户端每 10 分钟校验一次）。</p>
        <p class="doc-note"><b>对接建议：</b>软件启动时调用 verify 或 check-update；将 remaining_days / update_available 缓存本地并在需要时提示续费或更新；付款回调由系统处理，客户端只需轮询 orders/query。</p>
      </div>

      <div class="docs-card">
        <div class="docs-card-head"><span>常见问题</span></div>
        <ul>
          <li><b>付款后没有收到卡密？</b>在购买中心用订单号查询；App/IPTV 订单支付成功后会自动发卡（见「自动发卡」）；超过 15 分钟未支付订单自动作废。</li>
          <li><b>卡密提示已被使用？</b>卡密一次性绑定，若需更换设备请联系管理员重置绑定。</li>
          <li><b>支持退款吗？</b>卡密一经绑定激活不支持退款，购买前请确认商品信息。</li>
          <li><b>忘记绑定的机器码？</b>用卡密在「授权查询」中即可反查。</li>
          <li><b>客户端一直不提示更新？</b>请确认管理端已发布版本且状态启用；并传入 <code>client_version</code>；检查项目 API Key 是否正确。</li>
          <li><b>强制更新会拦住旧用户吗？</b>不会阻断授权，仅在客户端弹出强制更新提示；是否升级由客户端 UI 实现决定。</li>
          <li><b>如何成为代理？</b>通过 <router-link to="/agent/login" class="docs-link">代理登录入口</router-link> 注册，审核通过后可在额度内采购卡密。</li>
        </ul>
      </div>
    </div>

    <div class="docs-cta">
      <router-link to="/shop" class="cta-btn">立即购买 / 查看商品</router-link>
    </div>
  </main>

  <footer class="docs-footer">
    <span>© {{ year }} CardAuth · 轻量级卡密授权管理系统</span>
  </footer>
</div>
</template>

<script setup>
const year = new Date().getFullYear()
</script>

<style scoped>
.docs-app {
  position: relative; min-height: 100vh; overflow-x: hidden;
  background: linear-gradient(135deg, #f5f7fc 0%, #eef1fb 45%, #f8f5ff 100%);
}
.bg-canvas { position: fixed; inset: 0; pointer-events: none; z-index: 0; }
.bg-grid {
  position: absolute; inset: 0; opacity: .5;
  background-image: linear-gradient(rgba(115,103,240,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(115,103,240,.05) 1px, transparent 1px);
  background-size: 42px 42px;
}
.bg-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .35; }
.orb-a { width: 420px; height: 420px; background: #a78bfa; top: -120px; right: -80px; }
.orb-b { width: 360px; height: 360px; background: #60a5fa; bottom: -100px; left: -100px; }
.orb-c { width: 260px; height: 260px; background: #f0abfc; bottom: 30%; right: 25%; opacity: .2; }

/* 顶部栏 */
.top-bar {
  position: relative; z-index: 2;
  max-width: 1100px; margin: 0 auto;
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px; flex-wrap: wrap; gap: 12px;
}
.top-brand { display: flex; align-items: center; gap: 10px; color: #7367f0; }
.top-brand-text { display: flex; flex-direction: column; line-height: 1.3; }
.top-name { font-size: 18px; font-weight: 800; color: #333; letter-spacing: .4px; }
.top-sub { font-size: 12px; color: #8b93a7; }
.top-actions { display: flex; gap: 10px; }
.top-btn {
  display: inline-block; padding: 9px 18px; border-radius: 999px;
  font-size: 14px; font-weight: 600; text-decoration: none; transition: all .2s;
}
.btn-primary { background: linear-gradient(135deg, #7367f0, #9d67ff); color: #fff; box-shadow: 0 4px 14px rgba(115,103,240,.35); }
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(115,103,240,.45); }
.btn-ghost { border: 1px solid rgba(115,103,240,.4); color: #6758e8; background: #fff; }
.btn-ghost:hover { background: rgba(115,103,240,.08); }

/* 文档主体 */
.docs-wrap { position: relative; z-index: 1; max-width: 1100px; margin: 0 auto; padding: 18px 24px 40px; }
.docs-hero { text-align: center; padding: 26px 0 30px; }
.hero-title { font-size: 34px; font-weight: 800; margin: 0 0 12px; color: #2d2b45; letter-spacing: 1px; }
.hero-desc { font-size: 15px; color: #5f6580; margin: 0; }
.hero-desc b { color: #6758e8; }

.docs-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
.docs-card {
  background: rgba(255,255,255,.78);
  backdrop-filter: blur(14px);
  border: 1px solid rgba(255,255,255,.8);
  border-radius: 18px;
  padding: 22px 24px;
  box-shadow: 0 8px 30px rgba(102,126,234,.10);
  color: #333; font-size: 14px; line-height: 1.85;
}
.docs-card-head { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.docs-card-head::before { content: ''; width: 4px; height: 18px; border-radius: 3px; background: linear-gradient(#7367f0, #9d67ff); }
.docs-card-head span { font-weight: 400; color: #8b93a7; font-size: 12px; }
.docs-card-wide { grid-column: 1 / -1; }
.docs-sub { margin: 18px 0 8px; font-size: 14.5px; color: #4c40b8; }
.docs-code-title span { color: #6ee7b7; font-size: 11px; font-weight: 500; }
.docs-table-wrap { overflow-x: auto; margin: 8px 0; }
.docs-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.docs-table th,.docs-table td { border: 1px solid rgba(115,103,240,.18); padding: 7px 10px; text-align: left; }
.docs-table th { background: rgba(115,103,240,.08); color: #4c40b8; }
.doc-note { font-size: 12.5px; color: #8b93a7; }
.docs-link { color: #6758e8; }
.docs-card b { color: #4c40b8; }
.docs-card ul, .docs-card ol { padding-left: 20px; margin: 8px 0; }
.docs-card li { margin: 4px 0; }
.docs-card code {
  background: rgba(115,103,240,.10); color: #5b4fd0;
  padding: 1px 6px; border-radius: 6px; font-size: 12.5px;
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
}
.docs-tip {
  margin-top: 10px; padding: 10px 12px;
  background: rgba(255,183,77,.14); border-left: 3px solid #f5b04c;
  border-radius: 8px; font-size: 13px; color: #8a5a1f;
}
.docs-code {
  background: #2d2b45; color: #e6e6f0; border-radius: 12px;
  padding: 14px 16px; margin: 10px 0; overflow-x: auto;
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace; font-size: 12.5px; line-height: 1.7;
}
.docs-code-title { color: #9d94ff; font-weight: 600; margin-bottom: 6px; font-size: 12px; letter-spacing: .4px; }
.docs-code pre { margin: 0; white-space: pre-wrap; }

.docs-cta { text-align: center; padding: 30px 0 10px; }
.cta-btn {
  display: inline-block; padding: 13px 44px; border-radius: 999px;
  background: linear-gradient(135deg, #7367f0, #9d67ff); color: #fff;
  font-size: 15px; font-weight: 700; text-decoration: none;
  box-shadow: 0 6px 20px rgba(115,103,240,.4); transition: transform .2s;
}
.cta-btn:hover { transform: translateY(-2px) scale(1.02); }

.docs-footer { position: relative; z-index: 1; text-align: center; padding: 20px; color: #9aa0b5; font-size: 12.5px; }

@media (max-width: 860px) {
  .docs-grid { grid-template-columns: 1fr; }
  .hero-title { font-size: 26px; }
}
</style>
