<?php

/**
 * 商品分类管理控制器
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class CategoryController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $model = $this->model('Category');

        $this->view('categories/index', [
            'categories' => $model->allWithCount(),
            'csrf'       => $this->csrfToken(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->view('categories/create', [
            'csrf' => $this->csrfToken(),
            'old'  => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $model = $this->model('Category');
        [$data, $errors] = $model->sanitizeInput($_POST);

        if ($errors) {
            $this->view('categories/create', [
                'csrf'   => $this->csrfToken(),
                'old'    => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        $model->create($data);
        $this->setFlash('success', '分类「' . $data['name'] . '」已创建。');
        $this->redirect('/categories');
    }

    public function edit(int $id): void
    {
        $this->requireAuth();

        $model = $this->model('Category');
        $category = $model->find($id);
        if (!$category) {
            $this->setFlash('error', '分类不存在。');
            $this->redirect('/categories');
            return;
        }

        $this->view('categories/edit', [
            'csrf'     => $this->csrfToken(),
            'category' => $category,
            'old'      => $category,
            'errors'   => [],
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $model = $this->model('Category');
        $category = $model->find($id);
        if (!$category) {
            $this->setFlash('error', '分类不存在。');
            $this->redirect('/categories');
            return;
        }

        [$data, $errors] = $model->sanitizeInput($_POST, ['selfId' => $id]);

        if ($errors) {
            $this->view('categories/edit', [
                'csrf'     => $this->csrfToken(),
                'category' => $category,
                'old'      => $_POST,
                'errors'   => $errors,
            ]);
            return;
        }

        $model->update($id, $data);
        $this->setFlash('success', '分类「' . $data['name'] . '」已更新。');
        $this->redirect('/categories');
    }

    public function destroy(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $model = $this->model('Category');
        $category = $model->find($id);
        if (!$category) {
            $this->setFlash('error', '分类不存在。');
            $this->redirect('/categories');
            return;
        }

        $count = $model->productCount($id);
        $model->delete($id);

        $msg = '分类「' . $category['name'] . '」已删除。';
        if ($count > 0) {
            $msg .= ' ' . $count . ' 个商品的分类已清空（商品本身不受影响）。';
        }
        $this->setFlash('success', $msg);
        $this->redirect('/categories');
    }
}
