<?php /** Sidebar gaya Tailwind. Data dari Config\Menu. */ ?>
    <aside class="w-20 lg:w-24 bg-dark-800 border-r border-gray-800 flex flex-col items-center py-6 fixed inset-y-0 z-30 shadow-xl">
        <div class="mb-8 flex items-center justify-center">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 text-white font-bold text-xl">
                D
            </div>
        </div>
        <nav class="flex flex-col space-y-3 w-full px-3">
            <?php foreach (menu_items() as $item): ?>
                <?php $label = esc($item['label']); $mt = $item['bottom'] ? ' mt-auto' : ''; ?>
                <?php if ($item['active']): ?>
                    <a href="<?= esc($item['url'], 'attr') ?>" class="flex flex-col items-center justify-center py-3 px-2 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 shadow-inner group relative<?= $mt ?>" title="<?= esc($item['label'], 'attr') ?>" aria-current="page">
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-cyan-400 rounded-r"></div>
                        <i class="bi <?= esc($item['icon'], 'attr') ?> text-xl mb-1"></i>
                        <span class="text-[10px] font-semibold tracking-wide"><?= $label ?></span>
                    </a>
                <?php else: ?>
                    <a href="<?= esc($item['url'], 'attr') ?>" class="flex flex-col items-center justify-center py-3 px-2 rounded-xl text-gray-400 hover:text-cyan-400 hover:bg-dark-700/50 transition-all group<?= $mt ?>" title="<?= esc($item['label'], 'attr') ?>">
                        <i class="bi <?= esc($item['icon'], 'attr') ?> text-xl mb-1 group-hover:scale-110 transition-transform"></i>
                        <span class="text-[10px] font-medium tracking-wide"><?= $label ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
