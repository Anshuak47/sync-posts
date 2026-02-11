document.addEventListener("DOMContentLoaded", function () {
  const hostSection = document.getElementById("psync-host-section");
  const targetSection = document.getElementById("psync-target-section");
  const radios = document.querySelectorAll(".psync-mode-radio");

  function toggleSections() {
    let selected = "host";

    radios.forEach(function (radio) {
      if (radio.checked) {
        selected = radio.value;
      }
    });

    if (selected === "host") {
      hostSection.style.display = "block";
      targetSection.style.display = "none";
    } else {
      hostSection.style.display = "none";
      targetSection.style.display = "block";
    }
  }

  radios.forEach(function (radio) {
    radio.addEventListener("change", toggleSections);
  });

  toggleSections(); // Initial state

  const btn = document.getElementById("psynct-add-row");
  if (btn) {
    btn.addEventListener("click", function () {
      const tbody = document.getElementById("psynct-target-rows");
      const index = tbody.children.length;

      const row = `
            <tr>
                <td>
                    <input type="url"
                        name="<?php echo WP_PSYNCT_OPTION; ?>[targets][${index}][target_url]"
                        class="regular-text">
                </td>
                <td>
                    <input type="text"
                        readonly
                        name="<?php echo WP_PSYNCT_OPTION; ?>[targets][${index}][key]"
                        class="regular-text">
                </td>
            </tr>`;

      tbody.insertAdjacentHTML("beforeend", row);
    });
  }
});
