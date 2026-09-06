<?php
/** Model base CRUD tests — the generic Model.php helpers every model inherits.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function test_base_crud_on_customers(): void
{
    $c = new Customer();
    $id = $c->create([
        'name'  => 'CRUD Alice',
        'email' => 'alice@crud.test',
    ]);
    assertTrue($id > 0, 'create returns an id');

    $row = $c->find((int) $id);
    assertEquals('CRUD Alice', $row['name'], 'find returns created row');

    $byEmail = $c->findBy('email', 'alice@crud.test');
    assertEquals((int) $id, (int) $byEmail['id'], 'findBy works');

    assertTrue($c->update((int) $id, ['company' => 'Acme']), 'update returns true');
    $row = $c->find((int) $id);
    assertEquals('Acme', $row['company'], 'update persisted');

    assertEquals(1, $c->count('name = :n', [':n' => 'CRUD Alice']), 'count with where');

    assertTrue($c->delete((int) $id), 'delete returns true');
    assertEquals(false, $c->find((int) $id), 'row gone after delete');
}

function test_model_all_and_where(): void
{
    $c = new Customer();
    $c->create(['name' => 'Batch One', 'status' => 'active']);
    $c->create(['name' => 'Batch Two', 'status' => 'inactive']);
    $c->create(['name' => 'Batch Three', 'status' => 'active']);

    $all = $c->all('name ASC');
    assertEquals(3, count($all), 'all() returns everything');

    $active = $c->where('status', 'active', 'name ASC');
    assertEquals(2, count($active), 'where() filters');
}

function test_user_model_helpers(): void
{
    $u = new User();
    $admin = $u->findByEmail('admin@example.com');
    assertTrue($admin !== false, 'seeded admin exists');
    assertTrue($u->verifyPassword('password', $admin['password']), 'seeded admin password verifies');

    $newId = $u->register('Bob', 'bob@test.local', 'secret123');
    $bob = $u->find((int) $newId);
    assertEquals('sales', $bob['role'], 'default role is sales');
    assertTrue($u->verifyPassword('secret123', $bob['password']), 'register hashes the password');
    assertTrue(!$u->verifyPassword('wrong', $bob['password']), 'wrong password rejected');

    // Email is unique — second insert of same email must throw.
    $threw = false;
    try {
        $u->register('Bob2', 'bob@test.local', 'x');
    } catch (PDOException $e) {
        $threw = true;
    }
    assertTrue($threw, 'duplicate email rejected by unique constraint');
}

/** 客户搜索里的 % / _ 必须当字面量而不是 LIKE 通配符（回归：输入 % 会匹配整表） */
function test_customer_search_escapes_like_wildcards(): void
{
    $c = new Customer();
    $c->create(['name' => '100%棉纺', 'company' => 'C1', 'status' => 'active', 'owner_id' => 1]);
    $c->create(['name' => 'AB_CD 贸易', 'company' => 'C2', 'status' => 'active', 'owner_id' => 1]);
    $c->create(['name' => '普通客户', 'company' => 'C3', 'status' => 'active', 'owner_id' => 1]);

    // '%' 按字面量搜：只有名字里真带百分号的那条命中；修复前它匹配全部 3 条。
    $pct = $c->allWithOwner('%', 1, 15);
    assertEquals(1, count($pct), '百分号被当字面量，不会变成匹配整表的通配符');
    assertEquals('100%棉纺', $pct[0]['name'], '命中精确那一条');
    assertEquals(1, (int) $c->countWithOwner('%'), 'count 与列表同一份 WHERE');

    // '_' 同理
    $under = $c->allWithOwner('AB_CD', 1, 15);
    assertEquals(1, count($under), '下划线被当字面量');
    assertEquals('AB_CD 贸易', $under[0]['name'], '命中精确那一条');

    // 反斜杠也要安全：结尾反斜杠不该当转义符吞掉下一个字符
    $bs = $c->allWithOwner('100\\', 1, 15);
    assertEquals(0, count($bs), '结尾反斜杠搜索安全（此处无命中）');

    // 普通词搜索不受影响
    assertEquals(1, count($c->allWithOwner('普通客户', 1, 15)), '普通搜索照常');
}

runCase();
