@php
  $selectedDistrict = (string) old('district_id', $notice_board->district_id ?? '');
@endphp
<script>
  (function () {
    var division = document.getElementById('division_id');
    var district = document.getElementById('district_id');
    var summary = document.getElementById('activity-geography-summary');

    if (!division || !district || !summary) {
      return;
    }

    function syncActivityGeography(preferredDistrict) {
      var divisionId = String(division.value || '');
      var districtId = preferredDistrict === undefined
        ? String(district.value || '')
        : String(preferredDistrict || '');

      Array.prototype.forEach.call(district.querySelectorAll('option[data-division-id]'), function (option) {
        var available = divisionId !== '' && option.dataset.divisionId === divisionId;
        option.disabled = !available;
        option.hidden = !available;
      });

      var preferred = Array.prototype.find.call(district.options, function (option) {
        return option.value === districtId;
      });
      district.value = preferred && !preferred.disabled ? districtId : '';
      district.disabled = divisionId === '';
      district.options[0].textContent = divisionId === ''
        ? 'Choose a division first'
        : 'Entire selected division';

      var divisionName = division.options[division.selectedIndex].textContent.trim();
      var districtName = district.options[district.selectedIndex].textContent.trim();
      if (divisionId === '') {
        summary.innerHTML = '<strong>Activity scope:</strong> National — available in the countrywide directory.';
      } else if (district.value) {
        summary.innerHTML = '<strong>Activity scope:</strong> '
          + escapeText(districtName + ' District, ' + divisionName + ' Division') + '.';
      } else {
        summary.innerHTML = '<strong>Activity scope:</strong> '
          + escapeText(divisionName + ' Division') + ' — available to every district in this division.';
      }
    }

    function escapeText(value) {
      var element = document.createElement('span');
      element.textContent = value;
      return element.innerHTML;
    }

    division.addEventListener('change', function () { syncActivityGeography(''); });
    district.addEventListener('change', function () { syncActivityGeography(); });
    syncActivityGeography(@json($selectedDistrict));
  }());
</script>
