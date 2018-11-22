<?php namespace Phoenix; ?>
<div class="col-md-12 order-md-1 mb-3">
    <form class="needs-validation" method="POST" novalidate>
        <h4 class="mb-3">Deployment Actions</h4>
        <!--
        <hr class="mb-4">

        <div class="d-block my-3">
            <?php
        //template()->radios();
        ?>
        </div>
        -->
        <hr class="mb-4">
        <div class="row">
            <div class="col-md-6 mb-2">
                <h5>Create</h5>
                <?php template()->checkboxes('create'); ?>
            </div>
            <div class="col-md-6 mb-2">
                <h5>Delete</h5>
                <?php template()->checkboxes('delete'); ?>
            </div>
            <div class="col-md-6 mb-2">
                <h5>Update</h5>
                <?php template()->checkboxes('update'); ?>
            </div>
            <div class="col-md-6 mb-2">
                <h5>Transfer</h5>
                <?php template()->checkboxes('transfer'); ?>
            </div>
        </div>
        <hr class="mb-4">
        <button class="btn btn-primary btn-lg btn-block" type="submit">Do Stuff</button>
    </form>
</div>