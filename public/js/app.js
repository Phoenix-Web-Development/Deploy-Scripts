$(document).ready(function () {
    $('input[type=checkbox]').click(function () {
        // if is checked

        if ($(this).is(':checked')) {
            $(this).parents('div').children('input[type=checkbox]').prop('checked', true);
            $(this).parent().find('div input[type=checkbox]').prop('checked', true);

            //if ( $( this ).attr( 'id' ) == 'create' )
            //  $( 'input#delete[type=checkbox]' ).parent().find( 'div input[type=checkbox]' ).prop( 'checked', false );

            var parents = $(this).parents('div').children('input[type=checkbox]');
            $.each(parents, function (key, value) {
                var id = $(value).attr('id');
                var changee = [];
                switch (id) {
                    case 'create':
                        changee = ["delete", "update"];
                        break;
                    case 'delete':
                        changee = ["create", "update"];
                        break;
                    case 'update':
                        changee = ["create", "delete"];
                        break;
                }
                for (var i = 0; i < changee.length; i++) {
                    $('input#' + changee[i] + '[type=checkbox]').parent().find('input[type=checkbox]').prop('checked', false);
                }

            });

        } else {
            // uncheck all children
            $(this).parent().find('div input[type=checkbox]').prop('checked', false);
        }


    });
})//( jQuery );
