<?php
/**
 * Settings: app information + user profile, and the rule that makes profile
 * edits show up on other models (customers' 负责人, deals, orders, follow-ups).
 *
 * That rule is: a person's details are stored ONCE, in users. Every other table
 * keeps only users.id (owner_id / user_id / uploaded_by) and JOINs the name back
 * when reading. So there is nothing to "propagate" — but there is plenty to
 * regress: a future model that copies a name into its own column would silently
 * keep showing the old one. test_owner_information_is_never_copied() locks that
 * invariant down, and the profile tests assert the sync end to end.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

/** Render a view file with a given variable scope (like Controller::view does). */
function renderViewFile(string $viewFile, array $vars): string
{
    extract($vars);
    ob_start();
    require $viewFile;
    return ob_get_clean();
}

function makeStaff(string $email, string $name, string $password = 'password'): int
{
    $id = (new User())->register($name, $email, $password, 'sales');
    return (int) $id;
}

// ---------------------------------------------------------------- app settings

function test_settings_default_then_persist_and_flush(): void
{
    Setting::flushCache();
    $defaults = Setting::defaults();
    assertTrue(isset($defaults['app_name']), 'app_name has a default');

    assertEquals(APP_COPYRIGHT_UI, Setting::defaults()['copyright_notice'], 'copyright notice has a default');

    $original = Setting::values();
    try {
        (new Setting())->setMany(['app_name' => '环球贸易 CRM', 'currency_symbol' => '¥'], 1);
        assertEquals('环球贸易 CRM', appSetting('app_name'), 'edited value wins over default');
        assertEquals('环球贸易 CRM', appName(), 'appName() reads the setting');
        assertEquals('环球贸易 CRM', Setting::values()['app_name'], 'values() exposes it for the form');
        assertContains('Admin User', (new Setting())->changes()['app_name']['updated_by_name'] ?? '',
            'the edit is attributed to the user who made it (JOIN, not a copy)');
    } finally {
        (new Setting())->setMany(['app_name' => $original['app_name'], 'currency_symbol' => $original['currency_symbol']], 1);
    }
    assertEquals($original['app_name'], appSetting('app_name'), 'restored');
}

function test_money_follows_the_currency_setting(): void
{
    (new Setting())->setMany(['currency_symbol' => '$'], 1);
    assertEquals('$1,234.00', money(1234), 'default symbol');
    (new Setting())->setMany(['currency_symbol' => 'NT$'], 1);
    assertEquals('NT$1,234.00', money(1234), 'currency symbol is editable app info');
    (new Setting())->setMany(['currency_symbol' => '$'], 1);
}

function test_sanitize_filters_unknown_keys_and_bad_values(): void
{
    $clean = Setting::sanitize([
        'app_name'        => '  新名字  ',
        'currency_symbol' => 'not-a-symbol',
        'evil_key'        => 'anything',
        '_token'          => 'x',
    ]);
    assertEquals('新名字', $clean['values']['app_name'], 'trimmed');
    assertTrue(!array_key_exists('evil_key', $clean['values']), 'unknown keys cannot be written');
    assertEquals('$', $clean['values']['currency_symbol'], 'invalid select falls back to default');
    assertTrue(count($clean['errors']) === 1, 'invalid select reports one error');

    // An empty required field is refused but keeps the UI running on the default.
    $empty = Setting::sanitize(['app_name' => '   ']);
    assertTrue($empty['errors'] !== [], 'required app_name reports an error');
    assertEquals(APP_NAME, $empty['values']['app_name'], 'and falls back to the default');
}

function test_app_setting_survives_a_missing_table(): void
{
    // Views call money() / appName() everywhere; they must not 500 before an
    // admin has migrated the database.
    $before = Setting::values();
    $db = Database::connection();
    $db->exec('ALTER TABLE app_settings RENAME TO app_settings_hidden');
    Setting::flushCache();
    try {
        assertEquals($before['app_name'], appSetting('app_name'), 'falls back to the default with no table');
        assertEquals('$1.00', money(1), 'money still renders');
        assertEquals(null, appSetting('nope', null), 'unknown key returns the fallback');
        assertTrue(appName() !== '', 'appName() never returns empty');
    } finally {
        $db->exec('ALTER TABLE app_settings_hidden RENAME TO app_settings');
        Setting::flushCache();
    }
    assertEquals($before['app_name'], appSetting('app_name'), 'reads fine again');
}

// ------------------------------------------------------- profile + ownership sync

function test_profile_edit_is_visible_as_customer_owner(): void
{
    $uid  = makeStaff('sync-me@example.com', '旧姓名');
    $cid  = (new Customer())->create(['name' => '同步客户', 'status' => 'active', 'owner_id' => $uid]);
    $lid  = (new Lead())->create(['title' => '同步线索', 'status' => 'new', 'owner_id' => $uid]);
    $did  = (new Deal())->create(['title' => '同步商机', 'customer_id' => $cid, 'owner_id' => $uid]);
    $oid  = (new Order())->create(['order_number' => 'ORD-SYNC-0001', 'customer_id' => $cid,
                                   'title' => '同步订单', 'owner_id' => $uid]);

    $before = (new Customer())->findWithOwner((int) $cid);
    assertEquals('旧姓名', $before['owner_name'], 'owner shows the current name');

    $errors = (new User())->updateProfile((int) $uid, [
        'name'     => '  新姓名  ',
        'email'    => 'sync-me@example.com',
        'job_title' => '销售经理',
        'phone'    => '555-0199',
        'whatsapp' => '+8613800000000',
        'notes'    => '负责华东区域',
    ]);
    assertEquals([], $errors, 'profile update passes validation');

    // No copy step: every model resolves the person from users.id at read time.
    assertEquals('新姓名', (new Customer())->findWithOwner((int) $cid)['owner_name'], 'customer 负责人 synced');
    $leads = (new Lead())->allLeads('new');
    foreach ($leads as $lead) {
        if ((int) $lead['id'] === (int) $lid) {
            assertEquals('新姓名', $lead['owner_name'], 'lead list synced');
        }
    }
    foreach ((new Order())->allOrders() as $order) {
        if ((int) $order['id'] === (int) $oid) {
            assertEquals('新姓名', $order['owner_name'], 'order list synced');
        }
    }
    $deal = (new Deal())->find((int) $did);
    assertEquals((int) $cid, (int) $deal['customer_id'], 'deal still points at its customer');
    assertEquals((int) $uid, (int) $deal['owner_id'], 'deal still points at the same owner id');
    $customers = (new Customer())->allWithOwner();
    foreach ($customers as $customer) {
        if ((int) $customer['id'] === (int) $cid) {
            assertEquals('新姓名', $customer['owner_name'], 'customer list synced');
        }
    }

    // And the shared view helper used by detail pages.
    assertEquals('新姓名（销售经理）', ownerLabel($uid), 'ownerLabel shows name + 职位');
    $block = ownerBlock($uid);
    assertContains('新姓名', $block, 'ownerBlock shows the name');
    assertContains('555-0199', $block, 'ownerBlock shows the profile phone');
    assertTrue(strpos((new User())->find((int) $uid)['updated_at'] ?? '', '20') === 0, 'updated_at recorded');
}

function test_profile_validation_rejects_bad_input(): void
{
    $uid = makeStaff('valid-me@example.com', '可用');
    $other = makeStaff('taken@example.com', '别人');

    $model = new User();
    assertEquals(['姓名不能为空。'], $model->updateProfile((int) $uid, ['name' => '  ', 'email' => 'valid-me@example.com']),
        'empty name refused');
    $errors = $model->updateProfile((int) $uid, ['name' => '改名', 'email' => 'taken@example.com']);
    assertTrue($errors !== [], 'email already used by another account refused');
    assertTrue((new User())->find((int) $uid)['name'] === '可用', 'failed update changed nothing');
    assertTrue($model->updateProfile((int) $uid, ['name' => '改名', 'email' => 'valid-me@example.com']) === [],
        'own email is not treated as a conflict');
    assertEquals('改名', (new User())->find((int) $uid)['name'], 'rename applied');
}

function test_owned_reference_counts_tell_the_user_what_synced(): void
{
    $uid = makeStaff('counts@example.com', '计数员');
    $cid = (new Customer())->create(['name' => 'C1', 'status' => 'active', 'owner_id' => $uid]);
    (new Customer())->create(['name' => 'C2', 'status' => 'active', 'owner_id' => $uid]);
    (new Lead())->create(['title' => 'L1', 'status' => 'new', 'owner_id' => $uid]);
    (new FollowUp())->create(['customer_id' => (int) $cid, 'user_id' => $uid,
                              'type' => 'follow_up', 'title' => 'F1']);

    $refs = (new User())->ownedReferences((int) $uid);
    $byLabel = array_column($refs, 'count', 'label');
    assertEquals(2, $byLabel['客户（负责人）'], 'two customers');
    assertEquals(1, $byLabel['线索（负责人）'], 'one lead');
    assertEquals(1, $byLabel['跟进记录（记录人）'], 'one follow-up');
    assertEquals(4, (new User())->ownedReferenceCount((int) $uid), 'total across models');
}

function test_ownership_syncs_even_for_admin_edited_users(): void
{
    // The 负责人 shown on a customer is the users.name of the linked account.
    // Nothing caches it, so a change made by an admin on that account is picked
    // up on the next page view (no re-saving of the customer).
    $uid = makeStaff('admin-edit@example.com', '原名');
    $cid = (new Customer())->create(['name' => '管理员维护的客户', 'status' => 'active', 'owner_id' => $uid]);
    (new User())->update($uid, ['name' => '改名后']);
    assertEquals('改名后', (new Customer())->findWithOwner((int) $cid)['owner_name'], 'JOIN picks the new name up');
}

function test_password_change_verifies_against_the_new_hash(): void
{
    $uid  = makeStaff('pw@example.com', '密码测试', 'password');
    $user = new User();
    assertTrue($user->verifyPassword('password', $user->find((int) $uid)['password']), 'old password works');
    $user->updatePassword((int) $uid, 'brand-new-pw');
    $hash = $user->find((int) $uid)['password'];
    assertTrue($user->verifyPassword('brand-new-pw', $hash), 'new password works');
    assertTrue(!$user->verifyPassword('password', $hash), 'old password stopped working');
}

// ------------------------------------------------------------------------- invariants

function test_owner_information_is_never_copied(): void
{
    // This is what keeps the 设置 page honest: if somebody adds an
    // "owner_name"/"user_name" COLUMN to a business table, profile edits would
    // stop syncing for that model while still looking correct elsewhere.
    $db = Database::connection();
    $tables = $db->query(
        "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $suspects = ['owner_name', 'user_name', 'created_by_name', 'updated_by_name',
                 'sales_name', 'owner_label', 'contact_person_name', 'assigned_to_name'];
    foreach ($tables as $table) {
        $stmt = $db->prepare('SELECT name FROM pragma_table_info(:t)');
        $stmt->execute([':t' => $table]);
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($columns as $column) {
            assertTrue(!in_array(strtolower($column), $suspects, true),
                "table {$table} must not store a copy of a user's name in column {$column}");
        }
    }
}

function test_settings_page_renders_with_the_users_and_ownership_data(): void
{
    $staffId = makeStaff('render@example.com', '渲染测试');
    $adminId = (int) (new User())->register('渲染管理员', 'render-admin@example.com', 'password', 'admin');
    (new Customer())->create(['name' => '渲染客户', 'status' => 'active', 'owner_id' => $staffId]);

    $model = new User();
    $render = function (int $asId) use ($model): string {
        $_SESSION['user_id'] = $asId;
        $_SESSION['user'] = ['id' => $asId];
        return renderViewFile(APP_PATH . '/views/settings/index.php', [
            'user'        => $model->find($asId),
            'settings'    => Setting::values(),
            'definitions' => Setting::definitions(),
            'changes'     => (new Setting())->changes(),
            'references'  => $model->ownedReferences($asId),
            'tab'         => $asId === $_SESSION['user_id'] ? ($GLOBALS['render_tab'] ?? 'profile') : 'profile',
            'csrf'        => 'token',
        ]);
    };

    // --- as a plain sales account: no admin powers ---
    $GLOBALS['render_tab'] = 'profile';
    $html = $render((int) $staffId);
    assertContains('个人信息', $html, 'profile tab present');
    assertContains('信息同步范围', $html, 'sync panel present');
    assertContains('渲染测试', $html, 'current profile values are shown');
    assertContains('客户（负责人）', $html, 'the customer this person owns is listed');
    assertTrue(strpos($html, '应用信息') === false, 'sales users do not get the app-info tab');

    // --- as an admin: the app-info form is offered ---
    $GLOBALS['render_tab'] = 'app';
    $html = $render($adminId);
    assertContains('系统名称', $html, 'admin sees the app-name field');
    assertContains('货币符号', $html, 'admin sees the currency field');
    assertContains('应用信息', $html, 'admin sees the app-info tab');
    assertContains('恢复默认', $html, 'admin can reset the settings');

    unset($_SESSION['user_id'], $_SESSION['user'], $GLOBALS['render_tab']);
}

runCase();
