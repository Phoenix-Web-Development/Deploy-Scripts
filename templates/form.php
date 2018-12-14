<?php namespace Phoenix; ?>
<div class="col-md-12 order-md-1 mb-3">
    <form class="needs-validation" method="POST" novalidate>
        <div class="row mb-5">
            <div class="col-md-2 mb-2">
                <h4 class="mb-3">Config Files</h4>
                <hr class="mb-4">

                <?php
                template()->configRadios();
                ?>
            </div>
            <div class="col-md-10 mb-2">
                <h4 class="mb-3">Deployment Actions</h4>
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
                    <hr class="mb-4">
                </div>
            </div>
            <button class="btn btn-primary btn-lg m-auto py-2 px-5" type="submit">Do Stuff</button>
        </div>
    </form>
</div>