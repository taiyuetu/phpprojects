<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">线索</h3>
    <a href="<?= url('/leads/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> 新建线索</a>
</div>

<div class="card card-table">
    <div class="card-header bg-white d-flex gap-2">
        <?php $filters = ['' => '全部', 'new' => '新建', 'contacted' => '已联系', 'qualified' => '已确认', 'lost' => '已流失']; ?>
        <?php foreach ($filters as $value => $label): ?>
            <a href="<?= url('/leads' . ($value ? '?status=' . $value : '')) ?>"
               class="btn btn-sm <?= $status === $value ? 'btn-secondary' : 'btn-outline-secondary' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr class="text-muted small">
                    <th>线索</th>
                    <th>联系人</th>
                    <th>来源</th>
                    <th>预估金额</th>
                    <th>状态</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$leads): ?>
                <tr><td colspan="6" class="text-center text-muted p-4">未找到线索。</td></tr>
            <?php endif; ?>
            <?php foreach ($leads as $l): ?>
                <tr>
                    <td class="fw-semibold"><?= e($l['title']) ?></td>
                    <td><?= e($l['contact_name'] ?: '—') ?></td>
                    <td><?= e($l['source'] ?: '—') ?></td>
                    <td><?= money($l['value']) ?></td>
                    <td><?= statusBadge($l['status']) ?></td>
                    <td class="text-end">
                        <?php if ($l['status'] !== 'lost' && $l['status'] !== 'qualified'): ?>
                        <form method="POST" action="<?= url('/leads/' . $l['id'] . '/convert') ?>" class="d-inline"
                              onsubmit="return confirm('确定将此线索转为商机？系统将自动创建客户和商机记录。');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success" title="转为商机">
                                <i class="bi bi-arrow-right-circle"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <a href="<?= url('/leads/' . $l['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="<?= url('/leads/' . $l['id']) ?>" class="d-inline"
                              onsubmit="return confirm('确定删除此线索？');">
                            <input type="hidden" name="_method" value="DELETE">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
