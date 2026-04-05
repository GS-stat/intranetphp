$(document).ready(function() {
    
    // Auto-uppercase för regnummer
    $('#regnummer').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });
    
    // Visa/dölj däckförvaringsfält
    $('#dackforvaring').change(function() {
        if ($(this).is(':checked')) {
            $('#dackforvaringFields').slideDown();
        } else {
            $('#dackforvaringFields').slideUp();
            $('#dackforvaring_id').val('');
        }
    });
    
    // Kolla planerade jobb vid datumändring
    $('#planDate').on('change', function() {
        var datum = $(this).val();
        if (datum) {
            $('#planeradInfo').html('<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Kollar lediga tider...</span>');
            
            $.ajax({
                url: '../ajax/get_planerade_jobb.php',
                method: 'GET',
                data: { datum: datum },
                success: function(response) {
                    $('#planeradInfo').html(response);
                },
                error: function() {
                    $('#planeradInfo').html('<span class="text-danger">Kunde inte hämta information</span>');
                }
            });
        } else {
            $('#planeradInfo').empty();
        }
    });
    
    // Sökfunktion för tabeller
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
});