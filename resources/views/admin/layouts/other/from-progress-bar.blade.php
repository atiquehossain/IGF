<script src="http://malsup.github.com/jquery.form.js"></script>
<script>

    (function () {
        var progressBar = $('.progress-bar');
        var progress = $('.progress');
        var spinner = $('.spinner');
        var spinnerDIV = $('.spinner div');
        
        $('form .submit').ajaxForm({
            beforeSend: function () {
                progress.show();
                spinner.show();
                spinnerDIV.hide();
                var percentVal = '0%';
                progressBar.width(percentVal)
                progressBar.html(percentVal);
            },
            uploadProgress: function (event, position, total, percentComplete) {
                var percentVal = percentComplete + '%';
                progressBar.width(percentVal)
                progressBar.html(percentVal);
            },
            success: function () {
                 progress.hide();
                 spinner.hide();
                 spinnerDIV.show();
            },
            complete: function (xhr) {
                progress.hide();
                spinner.hide();
                spinnerDIV.show();
//                window.location.reload(true);
            }
        });
    })();
</script>
