<?php namespace Phoenix; ?>
<div class="col-md-12">
    <?php

    logger()->add('<h3>cPanel Staging Subdomains</h3>' . build_recursive_list((array)ph_d()->get_staging_subdomains()), 'light');
    logger()->add('<h3>Input Config Array</h3>' . build_recursive_list((array)ph_d()->config), 'light');

    logger()->display();
    ?>
</div>
</div>
</div>
<footer class="my-5 pt-5 text-muted text-center text-small">
    <p class="mb-1">&copy; 2018 Phoenix Web</p>
    <ul class="list-inline">
        <li class="list-inline-item"><a href="index.php" class="btn btn-primary btn-lg">Back to form</a></li>
    </ul>
</footer>
</body>
</html>