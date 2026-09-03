<?php
/** Model base CRUD tests — the generic Model.php helpers every model inherits. */
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

runCase();
