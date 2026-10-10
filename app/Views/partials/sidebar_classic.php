<?php /** Sidebar gaya klasik (CSS .sidebar didefinisikan di tiap halaman). Data dari Config\Menu. */ ?>
<div class="sidebar">
    <?php foreach (menu_items() as $item): ?>
        <a href="<?= esc($item['url'], 'attr') ?>" class="sidebar-item<?= $item['active'] ? ' active' : '' ?>" title="<?= esc($item['label'], 'attr') ?>"><i class="bi <?= esc($item['icon'], 'attr') ?>"></i></a>
    <?php endforeach; ?>
</div>
