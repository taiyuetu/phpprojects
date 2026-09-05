<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

/**
 * 商品库（主数据）。
 *
 * 这里只管目录本身；“明细必须从目录里选”这条约束落在
 * OrderItem::normalizeRows()（页面）与 Ai::checkItems()（AI）两处，
 * 两边共用同一个 Product::resolve()，所以不存在“页面能选、AI 选不了”的差。
 */
class ProductController extends Controller
{
    /** 选择框一次带出去的商品数：再多就该走搜索而不是塞进下拉 */
    private const PICK_LIMIT = 800;

    public function index(): void
    {
        $this->requireAuth();

        $q = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;

        $model = $this->model('Product');
        $total = $model->countAll($q, $status, $category);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        $this->view('products/index', [
            'products'   => $model->allPaged($q, $status, $category, $page, $perPage),
            'q'          => $q,
            'status'     => $status,
            'category'   => $category,
            'categories' => $model->categories(),
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'unlinked'   => $model->unlinkedItemCount(),
            // 列表页的“收编/删除”都要 CSRF token，别再让视图里 e(null)
            'csrf'       => $this->csrfToken(),
        ]);
    }

    /** 商品详情：资料 + 它卖出去的记录（价格改了也不影响历史订单，这里给的是快照合计） */
    public function show(string $id): void
    {
        $this->requireAuth();

        $model = $this->model('Product');
        $product = $model->find((int) $id);
        if (!$product) {
            $this->setFlash('error', '商品不存在。');
            $this->redirect('/products');
            return;
        }
        $product['public_code'] = $model->codeOf($product);

        $this->view('products/show', [
            'product' => $product,
            'usage'   => $model->usage((int) $id),
            'recent'  => $model->recentSales((int) $id, 20),
            'csrf'    => $this->csrfToken(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->view('products/create', [
            'csrf'  => $this->csrfToken(),
            'old'   => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        [$data, $errors] = Product::validateInput($_POST);
        if ($errors) {
            $this->view('products/create', ['csrf' => $this->csrfToken(), 'old' => $_POST, 'errors' => $errors]);
            return;
        }

        $data['owner_id'] = (int) $_SESSION['user_id'];
        $id = $this->model('Product')->create($data);
        $code = (new Product())->codeOf($this->model('Product')->find($id));
        $this->setFlash('success', '商品已创建：' . $code . '（' . $data['name'] . '）。');
        $this->redirect('/products');
    }

    public function edit(string $id): void
    {
        $this->requireAuth();

        $model = $this->model('Product');
        $product = $model->find((int) $id);
        if (!$product) {
            $this->setFlash('error', '商品不存在。');
            $this->redirect('/products');
            return;
        }
        if (!canManageResource($product['owner_id'] ?: null)) {
            $this->setFlash('error', '这个商品由同事建档，只有建档人或管理员能改。');
            $this->redirect('/products');
            return;
        }
        $product['public_code'] = $model->codeOf($product);

        $this->view('products/edit', [
            'product' => $product,
            'usage'   => $model->usage((int) $id),
            'csrf'    => $this->csrfToken(),
            'old'     => $product,
            'errors'  => [],
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $model = $this->model('Product');
        $product = $model->find((int) $id);
        if (!$product) {
            $this->setFlash('error', '商品不存在。');
            $this->redirect('/products');
            return;
        }
        if (!canManageResource($product['owner_id'] ?: null)) {
            $this->setFlash('error', '这个商品由同事建档，只有建档人或管理员能改。');
            $this->redirect('/products');
            return;
        }

        [$data, $errors] = Product::validateInput($_POST, (int) $id);
        if ($errors) {
            $data = array_merge((array) $product, array_map(static fn($v) => $v === null ? '' : $v, $data));
            $this->view('products/edit', [
                'product' => array_merge($product, ['public_code' => $model->codeOf($product)]),
                'usage'   => $model->usage((int) $id),
                'csrf'    => $this->csrfToken(),
                'old'     => $_POST,
                'errors'  => $errors,
            ]);
            return;
        }

        $model->update((int) $id, $data);
        $this->setFlash('success', '商品已更新：' . $model->codeOf($product) . '（' . $data['name'] . '）。'
            . '已成交订单里的名称与价格是按快照存的，不受本次改价影响。');
        $this->redirect('/products');
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $model = $this->model('Product');
        $product = $model->find((int) $id);
        if (!$product) {
            $this->setFlash('error', '商品不存在。');
            $this->redirect('/products');
            return;
        }
        if (!canManageResource($product['owner_id'] ?: null)) {
            $this->setFlash('error', '这个商品由同事建档，只有建档人或管理员能删。');
            $this->redirect('/products');
            return;
        }

        $usage = $model->usage((int) $id);
        if ($usage['items'] > 0) {
            // 快照留在明细里，所以删目录不会弄坏历史订单；但必须告知，否则人会以为订单少了东西
            $model->update((int) $id, ['status' => 'inactive']);
            $this->setFlash('warning', '这个商品已被 ' . $usage['items'] . ' 条明细（' . $usage['orders']
                . ' 张订单）引用，所以没有真删，只改成「停用」：以后选不到它，历史订单不受影响。'
                . '确认要彻底删除，请先让订单不再引用它。');
            $this->redirect('/products');
            return;
        }

        $model->delete((int) $id);
        $this->setFlash('success', '商品已删除（无人引用）。');
        $this->redirect('/products');
    }

    /** 把历史上手挨的明细名收编成商品（幂等，可重复点） */
    public function importItems(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $stat = $this->model('Product')->importUnlinkedItems();
        $this->setFlash('success', '已收编 ' . (int) $stat['created'] . ' 个新商品，关联 '
            . (int) $stat['linked'] . ' 条明细'
            . ((int) $stat['left'] > 0 ? '；还有 ' . (int) $stat['left'] . ' 条因名称为空未处理' : '，全部明细都已关联。'));
        $this->redirect('/products');
    }

    /**
     * 选择框的兜底搜索：商品数超过下拉里带出去的那一批时用这个。
     * 只回目录字段，不回成本/备注。
     */
    public function lookup(): void
    {
        $this->requireAuth();

        $q = trim((string) ($_GET['q'] ?? ''));
        $rows = (new Product())->pickList(self::PICK_LIMIT);
        if ($q !== '') {
            $needle = textLower($q);
            $rows = array_values(array_filter($rows, static function (array $p) use ($needle) {
                return str_contains(Product::haystack($p), $needle);
            }));
        }
        // 搜索结果用 order_id=0 之外的字段即可；限制条数，别把一次搜索变成全库导出
        $this->json([
            'total' => count($rows),
            'items' => array_slice($rows, 0, 50),
        ]);
    }
}
