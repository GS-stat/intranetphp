</main>
    
    <!-- jQuery (först) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Vår egen JavaScript -->
    <script src="../assets/js/script.js"></script>
    <?php if (!empty($extra_scripts)): ?>
        <?php foreach ($extra_scripts as $src): ?>
            <script src="<?php echo htmlspecialchars($src); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>