            <footer>
            <p>SIS_Controlix © <?php echo date('Y'); ?> | Sistema de Control Empresarial |Desarrollado por Onell Rodriguez </p>
        </footer>
    </main>

    <script src="../js/scripts.js"></script>
    
    <script>
    document.getElementById('logout-link').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('¿Seguro que deseas cerrar sesión?')) {
            window.location.href = 'login.php?logout=true';
        }
    });
    </script>
</body>
</html>