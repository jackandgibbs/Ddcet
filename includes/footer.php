        </div><!-- .page-content -->
    </main>
</div><!-- .app-layout -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<style>.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:150;}@media(max-width:768px){.sidebar.open~.sidebar-overlay,.sidebar-overlay.show{display:block;}}</style>
<script>
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');

function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
}

document.querySelector('.menu-toggle').addEventListener('click', function() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
});

// Close sidebar when clicking a nav link (mobile)
sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', closeSidebar));
</script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>