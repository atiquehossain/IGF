<script src="{{ asset('admin-assets/assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('admin-assets/assets/js/popper.min.js') }}"></script>
<script src="{{ asset('admin-assets/assets/js/plugins.js') }}"></script>
<script src="{{ asset('admin-assets/assets/js/main.js') }}"></script>


<script src="{{ asset('admin-assets/assets/js/toastr.min.js') }}"></script>
<script src="{{ asset('admin-assets/assets/js/datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>

<script>
    /* If browser back button was used, flush cache */
    (function() {
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    })();
    $(document).ready(function() {
        $('.custom-success').fadeIn().delay(5000).fadeOut();
        $('.custom-error').fadeIn().delay(5000).fadeOut();

        $('#menuToggle').on('click', function() {
            window.setTimeout(function() {
                $('#menuToggle').attr('aria-expanded', $('#left-panel').is(':visible') && ($(window).width() < 760 || $('#left-panel').hasClass('open-menu')) ? 'true' : 'false');
            }, 20);
        });

        $('#sidebarClose').on('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            if ($(window).width() < 760) {
                $('#left-panel').slideUp(180);
            } else {
                $('#left-panel').removeClass('open-menu');
            }
            $('#menuToggle').attr('aria-expanded', 'false').focus();
        });

        $('.right-panel table').each(function() {
            var table = $(this);
            if (!table.parent().hasClass('table-responsive')) {
                table.wrap('<div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable records table"></div>');
            }

            var body = table.children('tbody');
            if (body.length && body.children('tr').length === 0) {
                var columnCount = Math.max(1, table.find('thead th').length);
                body.append('<tr><td class="text-center py-4" colspan="' + columnCount + '">No records found.</td></tr>');
            }

            if (/actions?/i.test($.trim(table.find('thead th').last().text()))) {
                body.children('tr').each(function() {
                    var actionCell = $(this).children('td').last();
                    if (actionCell.length && $.trim(actionCell.text()) === '' && actionCell.find('a,button,input').length === 0) {
                        actionCell.append('<span class="badge badge-light">View only</span>');
                    }
                });
            }
        });
    });

    $(function() {
        $('[data-toggle="tooltip"]').tooltip();
        $.fn.datepicker.defaults.format = "dd-mm-yyyy";

        $(".datepicker").datepicker({
            format: 'dd-mm-yyyy',
            autoclose: true
        });
    });

    function printDiv(divName) {
        var printTarget = document.getElementById(divName);
        if (!printTarget) {
            return;
        }

        var style = document.getElementById('igf-scoped-print-style');
        if (!style) {
            style = document.createElement('style');
            style.id = 'igf-scoped-print-style';
            style.textContent = '@media print {' +
                'body.igf-print-scope * { visibility: hidden !important; }' +
                'body.igf-print-scope .igf-print-target,' +
                'body.igf-print-scope .igf-print-target * { visibility: visible !important; }' +
                'body.igf-print-scope .igf-print-target {' +
                'position: absolute !important; inset: 0 auto auto 0 !important; width: 100% !important;' +
                '}' +
                '}';
            document.head.appendChild(style);
        }

        document.body.classList.add('igf-print-scope');
        printTarget.classList.add('igf-print-target');
        try {
            window.print();
        } finally {
            printTarget.classList.remove('igf-print-target');
            document.body.classList.remove('igf-print-scope');
        }
    }

    function printElement(divName) {
        $('#' + divName).css('display', 'block');
        var elem = document.getElementById(divName);

        var domClone = elem.cloneNode(true);

        var $printSection = document.getElementById("printSection");

        if (!$printSection) {
            var $printSection = document.createElement("div");
            $printSection.id = "printSection";
            document.body.appendChild($printSection);
        }

        $printSection.innerHTML = "";
        $printSection.appendChild(domClone);
        window.print();
    }

    function changefile(event, id, exptenton) {
        if (event.target.files.length > 0) {
            let file = event.target.files[0];
            let file_type = file.name.split('.').pop();

            let fileReader = new FileReader();
            fileReader.readAsDataURL(file); // read file as data url
            fileReader.onload = function() {
                //   alert('Yes');
                // called once readAsDataURL is completed
                // this.uploadImges = fileReader.result;
                let output = document.getElementById(id);
                output.src = fileReader.result;
                var fileExtension = exptenton ?? ["csv", "xls", "pdf", 'xlsx', 'docx', 'doc'];
                if (fileExtension.indexOf(file_type) > -1) {
                    output.src = `/image/${file_type}.png`;
                }
            };
        }
    }

    $(".copy-text").click(function() {
        var route = $(this).data("route");
        var sampleTextarea = document.createElement("textarea");
        document.body.appendChild(sampleTextarea);
        sampleTextarea.value = route;
        sampleTextarea.select();
        document.execCommand("copy");
        document.body.removeChild(sampleTextarea);
        toastrMsg('info', 'Copy : ' + route);
    });

    window.addEventListener("load", (event) => {
        $('.btn_disabled_load').removeAttr('disabled');
        setAdminBusy(false);
    });

    function setAdminBusy(isBusy) {
        var spinner = $('.spinner');
        spinner.toggle(Boolean(isBusy)).attr('aria-hidden', isBusy ? 'false' : 'true');
        $('#admin-content').attr('aria-busy', isBusy ? 'true' : 'false');
    }

    function adminErrorMessage(error) {
        if (error && error.responseJSON && typeof error.responseJSON.message === 'string' && error.responseJSON.message.trim()) {
            return error.responseJSON.message.trim();
        }
        if (error && typeof error.statusText === 'string' && error.statusText.trim()) {
            return 'The request could not be completed: ' + error.statusText.trim() + '.';
        }
        return 'The request could not be completed. Check your connection and try again.';
    }

    // Wait until every submit listener (including inline confirmation and AJAX
    // handlers) has run before showing the page-navigation overlay. Showing it
    // synchronously leaves the whole admin UI blocked when a later listener
    // cancels the submission.
    $(document).on('submit.adminBusy', 'form', function(event) {
        var form = this;
        window.setTimeout(function() {
            if (event.isDefaultPrevented() || $(form).is('[data-no-busy]')) {
                return;
            }

            setAdminBusy(true);
            if ($(form).attr('target') === '_blank') {
                window.setTimeout(function() {
                    setAdminBusy(false);
                }, 1500);
            }
        }, 0);
    });

    $("#main-menu .sub-menu a,#main-menu .menu-item a ,.navbar-header .navbar-brand,.user-menu.dropdown-menu a, .table a .fa-eye")
        .click(function(event) {
            var link = this;
            window.setTimeout(function() {
                if (event.isDefaultPrevented()) {
                    return;
                }

                setAdminBusy(true);
                if ($(link).attr('target') === '_blank') {
                    window.setTimeout(function() {
                        setAdminBusy(false);
                    }, 1500);
                }
            }, 0);
        });

    function itemDelete({
        tableId,
        method
    }) {
        $('#' + tableId + ' tbody').on('click', '.trash', function(event) {
            event.preventDefault();
            var button = $(this);
            if (button.prop('disabled')) {
                return;
            }
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            var url = button.data('url');
            var id = button.data('id');
            var itemLabel = String(button.data('item-label') || ('record #' + id));
            var row = $('#' + tableId + ' tr#' + id);
            var isConfirm = window.confirm('Delete ' + itemLabel + '? This cannot be undone unless the item supports recovery from Trash.');
            if (isConfirm) {
                button.prop('disabled', true).attr('aria-busy', 'true');
                setAdminBusy(true);
                $.ajax({
                    type: method,
                    url: url,
                    success: function(res) {
                        toastrMsg('success', res.message);
                        var nextControl = row.next('tr').find('button, a, input, select').filter(':visible').first();
                        row.remove();
                        if (nextControl.length) {
                            nextControl.trigger('focus');
                        } else {
                            $('#' + tableId).attr('tabindex', '-1').trigger('focus');
                        }
                    },
                    error: function(err) {
                        toastrMsg('error', adminErrorMessage(err));
                    },
                    complete: function() {
                        button.prop('disabled', false).removeAttr('aria-busy');
                        setAdminBusy(false);
                    }
                });
            }
        });
    }

    function itemStatus({
        tableId,
        method
    }) {
        $('#' + tableId + ' tbody').on('click', '.status', function(event) {
            event.preventDefault();
            var button = $(this);
            if (button.prop('disabled')) {
                return;
            }
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            button.prop('disabled', true).attr('aria-busy', 'true');
            setAdminBusy(true);

            var url = button.data('url');
            var id = button.data('id');

            $.ajax({
                type: method,
                url: url,
                success: function(res) {
                    var statusIcon = button.find('i.fa').first();
                    toastrMsg('success', res.message);
                    var pressedState = button.attr('aria-pressed');
                    var wasPublished = pressedState === 'true' ||
                        (pressedState !== 'false' && (statusIcon.hasClass('fa-check-square') || statusIcon.hasClass('fa-eye-slash')));
                    var hasResponseStatus = res.status === true || res.status === false ||
                        res.status === 1 || res.status === 0 || res.status === '1' || res.status === '0';
                    var isPublished = hasResponseStatus
                        ? (res.status === true || res.status === 1 || res.status === '1')
                        : !wasPublished;

                    var currentAccessibleLabel = String(button.attr('aria-label') || '');
                    var usesActivationLanguage = /\b(?:activate|deactivate)\b/i.test(currentAccessibleLabel + ' ' + button.text());
                    var activeAction = String(button.data('active-action') || (usesActivationLanguage ? 'Deactivate' : 'Unpublish'));
                    var inactiveAction = String(button.data('inactive-action') || (usesActivationLanguage ? 'Activate' : 'Publish'));
                    var actionLabel = isPublished ? activeAction : inactiveAction;
                    var itemLabel = String(button.data('item-label') || currentAccessibleLabel.replace(/^\s*(?:unpublish|publish|deactivate|activate)\s*/i, '') || 'item');
                    var activeIcon = String(button.data('active-icon') || (statusIcon.hasClass('fa-eye') || statusIcon.hasClass('fa-eye-slash') ? 'fa-eye-slash' : 'fa-check-square'));
                    var inactiveIcon = String(button.data('inactive-icon') || (activeIcon === 'fa-eye-slash' ? 'fa-eye' : 'fa-square'));

                    statusIcon.removeClass('fa-check-square fa-square fa-eye fa-eye-slash')
                        .addClass(isPublished ? activeIcon : inactiveIcon);
                    var visibleLabel = button.find('span').filter(function() {
                        return !$(this).hasClass('sr-only');
                    }).first();
                    if (visibleLabel.length) {
                        visibleLabel.text(actionLabel);
                    } else {
                        var directTextNodes = button.contents().filter(function() {
                            return this.nodeType === 3 && $.trim(this.nodeValue).length > 0;
                        });
                        if (directTextNodes.length) {
                            directTextNodes.last()[0].nodeValue = ' ' + actionLabel;
                        }
                    }
                    button.attr('aria-pressed', isPublished ? 'true' : 'false');
                    button.attr('aria-label', actionLabel + ' ' + itemLabel);
                    button.attr('title', actionLabel + ' ' + itemLabel);
                },
                error: function(err) {
                    toastrMsg('error', adminErrorMessage(err));
                },
                complete: function() {
                    button.prop('disabled', false).removeAttr('aria-busy');
                    setAdminBusy(false);
                }
            });

        });
    }

    function getProjectSectors(event, route, lang, selectId, placheHolder = 'Select Option') {
        const id = event.target.value;
        const sectorSelect = $(`#${selectId}-${lang}`);

        sectorSelect.empty(); // Clear the existing options
        sectorSelect.append(new Option(`${placheHolder}`, ''));
        setAdminBusy(true);

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: `${route}/${id}/${lang}`,
            success: function(res) {
                if (res.data) {
                    $.each(res.data, function(index, sector) {
                        sectorSelect.append(new Option(sector.name, sector.id));
                    });
                }
            },
            error: function(err) {
                toastrMsg('error', adminErrorMessage(err));
            },
            complete: function() {
                setAdminBusy(false);
            }
        });
    }


    function toastrMsg(type, msg) {
        toastr.options = {
            "closeButton": false,
            "debug": false,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "3000",
            "extendedTimeOut": "1000",
            "escapeHtml": true,
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        const toastType = ['info', 'warning', 'success', 'error'].includes(type) ? type : 'info';
        toastr[toastType](String(msg ?? ''));
    }
</script>
