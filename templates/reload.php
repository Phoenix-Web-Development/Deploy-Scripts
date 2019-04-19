<?php namespace Phoenix; ?>
<div class="col-md-12 order-md-1 mb-3">
    <form class="needs-validation" method="POST" novalidate>
        <hr class="mb-4">
        <?php $config_selected = ph_d()->configControl->getConfigSelected(); ?>
        <input class="form-check-input d-none" type="radio" name="config-select"
               id="<?php echo $config_selected['name']; ?>"
               value="<?php echo $config_selected['name']; ?>" checked>
        <button class="btn btn-primary btn-lg btn-block" type="submit">
            Reload <?php echo ucfirst($config_selected['name']); ?> Config
        </button>
    </form>
</div>