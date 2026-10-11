<?php /** Sidebar gaya Tailwind. Data dari Config\Menu. */ ?>
    <aside class="w-20 lg:w-24 bg-dark-800 border-r border-gray-800 flex flex-col items-center py-6 fixed inset-y-0 z-30 shadow-xl">
        <div class="mb-8 flex items-center justify-center">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 text-white font-bold text-xl">
                D
            </div>
        </div>
        <nav class="flex flex-col space-y-3 w-full px-3 flex-1">
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
        <div class="w-full px-3 mt-auto pt-4 border-t border-gray-800/50">
            <button onclick="lockScreen()" class="w-full flex flex-col items-center justify-center py-3 px-2 rounded-xl text-gray-400 hover:text-amber-400 hover:bg-amber-500/10 transition-all group mb-2" title="Lock Screen">
                <i class="bi bi-lock text-xl mb-1 group-hover:scale-110 transition-transform"></i>
                <span class="text-[10px] font-medium tracking-wide">Lock</span>
            </button>
            <a href="<?= base_url('logout') ?>" class="flex flex-col items-center justify-center py-3 px-2 rounded-xl text-gray-400 hover:text-rose-400 hover:bg-rose-500/10 transition-all group" title="Logout">
                <i class="bi bi-box-arrow-right text-xl mb-1 group-hover:scale-110 transition-transform"></i>
                <span class="text-[10px] font-medium tracking-wide">Logout</span>
            </a>
        </div>
    </aside>

    <div id="lockScreenOverlay" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-dark-900/95 backdrop-blur-sm">
        <div class="text-center">
            <i class="bi bi-lock text-6xl text-cyan-500 mb-4"></i>
            <h2 class="text-2xl font-bold text-white mb-2">Screen Locked</h2>
            <p class="text-gray-400 mb-6">Enter your password to unlock</p>
            <input type="password" id="unlockPassword" placeholder="Password" class="px-4 py-2 rounded-lg bg-dark-800 border border-gray-700 text-white focus:outline-none focus:border-cyan-500 mb-4 w-64">
            <br>
            <button onclick="unlockScreen()" class="px-6 py-2 rounded-lg bg-cyan-600 text-white font-medium hover:bg-cyan-500">UNLOCK</button>
        </div>
    </div>

    <script>
        // Check lock state on load (localStorage persists across tabs/windows)
        if (localStorage.getItem('isLocked') === 'true') {
            document.getElementById('lockScreenOverlay').classList.remove('hidden');
        }

        function lockScreen() {
            localStorage.setItem('isLocked', 'true');
            document.getElementById('lockScreenOverlay').classList.remove('hidden');
            document.getElementById('unlockPassword').focus();
        }

        function unlockScreen() {
            const pass = document.getElementById('unlockPassword').value;
            if (!pass) {
                alert('Please enter password');
                return;
            }
            
            fetch(`${baseUrl}auth/verify-password`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ password: pass })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    localStorage.removeItem('isLocked');
                    document.getElementById('lockScreenOverlay').classList.add('hidden');
                    document.getElementById('unlockPassword').value = '';
                } else {
                    alert(data.message || 'Incorrect password');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error verifying password');
            });
        }
    </script>
