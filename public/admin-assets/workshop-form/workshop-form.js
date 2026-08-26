(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    }

    ready(function () {
        var form = document.querySelector('[data-workshop-form]');
        if (!form) {
            return;
        }

        var attendance = document.getElementById('attendance_mode');
        var registrationMode = document.getElementById('registration_mode');
        var meetingGroup = form.querySelector('[data-meeting-field]');
        var meetingInput = document.getElementById('private_meeting_url');
        var staleMeetingWarning = form.querySelector('[data-stale-meeting-warning]');
        var venueNames = form.querySelectorAll('[data-venue-name-label]');
        var venueAddresses = form.querySelectorAll('[data-venue-address-label]');
        var venueMarkers = form.querySelectorAll('[data-venue-required-marker]');
        var venueInputs = [
            document.getElementById('en-venue'),
            document.getElementById('en-address'),
            document.getElementById('bn-venue'),
            document.getElementById('bn-address')
        ].filter(Boolean);
        var capacity = document.getElementById('capacity');
        var capacityGroup = form.querySelector('[data-capacity-field]');
        var capacityUnlimited = document.getElementById('capacity-unlimited');
        var capacityLimited = document.getElementById('capacity-limited');
        var modeHelp = form.querySelector('[data-registration-mode-help]');
        var registrationOpens = document.getElementById('registration_opens_at');
        var registrationCloses = document.getElementById('registration_closes_at');
        var workshopStarts = document.getElementById('starts_at');
        var workshopEnds = document.getElementById('ends_at');
        var visibleFrom = document.getElementById('visible_from_at');
        var scheduleSummary = document.getElementById('workshop-schedule-summary');
        var durationButton = document.getElementById('workshop-set-duration');
        var submitting = false;
        var dirty = false;

        function toggleAttendanceFields() {
            var mode = attendance ? attendance.value : '';
            var physical = mode === 'offline' || mode === 'hybrid';
            var hasStoredMeetingLink = meetingInput && meetingInput.value.trim() !== '';

            venueInputs.forEach(function (input) {
                input.required = physical;
            });
            venueMarkers.forEach(function (marker) {
                marker.hidden = !physical;
            });
            venueNames.forEach(function (label) {
                label.textContent = mode === 'online' ? 'Platform name' : 'Venue name';
            });
            venueAddresses.forEach(function (label) {
                label.textContent = mode === 'online' ? 'Online location note' : 'Venue address';
            });

            if (meetingGroup) {
                meetingGroup.hidden = mode !== 'online' && mode !== 'hybrid' && !hasStoredMeetingLink;
            }
            if (staleMeetingWarning) {
                staleMeetingWarning.hidden = !(mode === 'offline' && hasStoredMeetingLink);
            }
        }

        function registrationExplanation(mode) {
            if (mode === 'automatic') {
                return 'Each valid submission is confirmed immediately while space is available.';
            }
            if (mode === 'manual') {
                return 'Each submission starts as Pending so HR can review and confirm it.';
            }
            if (mode === 'waitlist') {
                return 'People are confirmed until the limit is reached; later submissions join the waitlist.';
            }

            return 'Choose a method to see what it means.';
        }

        function toggleCapacity(clearUnlimitedValue) {
            var waitlist = registrationMode && registrationMode.value === 'waitlist';
            if (waitlist && capacityLimited) {
                capacityLimited.checked = true;
            }
            if (capacityUnlimited) {
                capacityUnlimited.disabled = waitlist;
            }

            var limited = capacityLimited && capacityLimited.checked;
            if (capacityGroup) {
                capacityGroup.hidden = !limited;
            }
            if (capacity) {
                capacity.required = Boolean(limited);
                if (!limited && clearUnlimitedValue) {
                    capacity.value = '';
                }
            }
            if (modeHelp) {
                modeHelp.textContent = registrationExplanation(registrationMode ? registrationMode.value : '');
            }
        }

        function clearScheduleValidity() {
            [visibleFrom, registrationOpens, registrationCloses, workshopStarts, workshopEnds].forEach(function (input) {
                if (input) {
                    input.setCustomValidity('');
                }
            });
        }

        function localTimestamp(value) {
            return value ? new Date(value).getTime() : null;
        }

        function readableLocalDate(value) {
            if (!value || value.indexOf('T') === -1) {
                return '';
            }

            var parts = value.split('T');
            var date = parts[0].split('-').map(Number);
            var time = parts[1].split(':').map(Number);
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var hour = time[0] % 12 || 12;
            var minute = String(time[1] || 0).padStart(2, '0');
            var period = time[0] >= 12 ? 'PM' : 'AM';

            return date[2] + ' ' + months[date[1] - 1] + ' ' + date[0] + ', ' + hour + ':' + minute + ' ' + period;
        }

        function updateSchedule() {
            clearScheduleValidity();

            if (registrationOpens && registrationCloses) {
                registrationCloses.min = registrationOpens.value || '';
            }
            if (registrationCloses && workshopStarts) {
                workshopStarts.min = registrationCloses.value || '';
            }
            if (workshopStarts && workshopEnds) {
                workshopEnds.min = workshopStarts.value || '';
            }

            var opens = localTimestamp(registrationOpens && registrationOpens.value);
            var closes = localTimestamp(registrationCloses && registrationCloses.value);
            var starts = localTimestamp(workshopStarts && workshopStarts.value);
            var ends = localTimestamp(workshopEnds && workshopEnds.value);
            var visible = localTimestamp(visibleFrom && visibleFrom.value);
            var error = '';

            if (opens !== null && closes !== null && opens >= closes) {
                error = 'Registration must close after it opens.';
                registrationCloses.setCustomValidity(error);
            } else if (closes !== null && starts !== null && closes > starts) {
                error = 'The registration deadline must be before or exactly when the workshop starts.';
                registrationCloses.setCustomValidity(error);
            } else if (starts !== null && ends !== null && starts >= ends) {
                error = 'The workshop end time must be after the start time.';
                workshopEnds.setCustomValidity(error);
            } else if (visible !== null && opens !== null && visible > opens) {
                error = 'The public page must be visible before registration opens.';
                visibleFrom.setCustomValidity(error);
            }

            if (!scheduleSummary) {
                return;
            }

            scheduleSummary.classList.remove('is-valid', 'is-invalid');
            if (error) {
                scheduleSummary.classList.add('is-invalid');
                scheduleSummary.textContent = error;
                return;
            }

            if ([registrationOpens, registrationCloses, workshopStarts, workshopEnds].some(function (input) {
                return !input || !input.value;
            })) {
                scheduleSummary.textContent = 'Enter the four required dates to check the schedule.';
                return;
            }

            scheduleSummary.classList.add('is-valid');
            scheduleSummary.textContent =
                'Registration: ' + readableLocalDate(registrationOpens.value) +
                ' to ' + readableLocalDate(registrationCloses.value) +
                '. Workshop: ' + readableLocalDate(workshopStarts.value) +
                ' to ' + readableLocalDate(workshopEnds.value) +
                '. All times are Bangladesh time.';
        }

        function dateTimeLocalValue(date) {
            function pad(value) {
                return String(value).padStart(2, '0');
            }

            return date.getFullYear() + '-' +
                pad(date.getMonth() + 1) + '-' +
                pad(date.getDate()) + 'T' +
                pad(date.getHours()) + ':' +
                pad(date.getMinutes());
        }

        if (attendance) {
            attendance.addEventListener('change', toggleAttendanceFields);
        }
        if (meetingInput) {
            meetingInput.addEventListener('input', toggleAttendanceFields);
        }
        if (registrationMode) {
            registrationMode.addEventListener('change', function () {
                toggleCapacity(false);
            });
        }
        [capacityUnlimited, capacityLimited].forEach(function (radio) {
            if (radio) {
                radio.addEventListener('change', function () {
                    toggleCapacity(radio === capacityUnlimited);
                });
            }
        });
        [visibleFrom, registrationOpens, registrationCloses, workshopStarts, workshopEnds].forEach(function (input) {
            if (input) {
                input.addEventListener('input', updateSchedule);
                input.addEventListener('change', updateSchedule);
            }
        });

        if (durationButton && workshopStarts && workshopEnds) {
            durationButton.addEventListener('click', function () {
                if (!workshopStarts.value) {
                    workshopStarts.focus();
                    workshopStarts.reportValidity();
                    return;
                }

                var end = new Date(workshopStarts.value);
                end.setMinutes(end.getMinutes() + 90);
                workshopEnds.value = dateTimeLocalValue(end);
                workshopEnds.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        form.querySelectorAll('[data-insert-workshop-image]').forEach(function (button) {
            button.addEventListener('click', function () {
                var editorId = button.getAttribute('data-insert-workshop-image');
                var editor = window.tinymce && window.tinymce.get(editorId);
                if (editor) {
                    editor.focus();
                    editor.execCommand('mceImage');
                    return;
                }

                var textarea = document.getElementById(editorId);
                if (textarea) {
                    textarea.focus();
                }
                window.alert('The editor is still loading. Please try the image button again in a moment.');
            });
        });

        form.addEventListener('input', function () {
            dirty = true;
        });
        form.addEventListener('change', function () {
            dirty = true;
        });
        form.addEventListener('submit', function (event) {
            if (window.tinymce) {
                window.tinymce.triggerSave();
            }
            updateSchedule();

            if (!form.checkValidity()) {
                event.preventDefault();
                var firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.reportValidity();
                }
                return;
            }

            if (form.getAttribute('data-live-edit') === '1') {
                var registrations = Number(form.getAttribute('data-registration-count') || 0);
                var message = 'Save these changes to the live public workshop now?';
                if (registrations > 0) {
                    message += ' This workshop already has ' + registrations + ' registration' + (registrations === 1 ? '' : 's') + '.';
                }
                if (!window.confirm(message)) {
                    event.preventDefault();
                    return;
                }
            }

            submitting = true;
        });

        window.addEventListener('beforeunload', function (event) {
            if (!dirty || submitting) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });

        toggleAttendanceFields();
        toggleCapacity(false);
        updateSchedule();

        var errorSummary = document.getElementById('workshop-form-errors');
        if (errorSummary) {
            window.setTimeout(function () {
                errorSummary.focus();
            }, 0);
        }
    });
}());
