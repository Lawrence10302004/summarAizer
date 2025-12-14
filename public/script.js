document.addEventListener("DOMContentLoaded", () => {
  const textInput = document.getElementById("textInput");
  const pasteBtn = document.getElementById("pasteBtn");
  const clearBtn = document.getElementById("clearBtn");
  const summarizeBtn = document.getElementById("summarizeBtn");
  const wordCount = document.getElementById("wordCount");
  const summaryBox = document.getElementById("summaryBox");
  const copyBtn = document.getElementById("copyBtn");
  const tabs = document.querySelectorAll(".tab");
  const slider = document.getElementById("lengthSlider");
  const lengthLabel = document.getElementById("lengthLabel");
  const themeToggle = document.getElementById("themeToggle");

  let selectedMode = "paragraph";
  let selectedLength = "short";

  /* ----------- MODE SWITCH ----------- */
  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      tabs.forEach((t) => t.classList.remove("active"));
      tab.classList.add("active");
      selectedMode = tab.dataset.mode; // "paragraph" or "bullet"
    });
  });

  /* ----------- LENGTH SLIDER ----------- */
  slider.addEventListener("input", () => {
    selectedLength = slider.value == 1 ? "short" :
                     slider.value == 2 ? "medium" : "long";
    lengthLabel.textContent =
      selectedLength.charAt(0).toUpperCase() + selectedLength.slice(1);
  });

  /* ----------- WORD COUNT ----------- */
  textInput.addEventListener("input", () => {
    const words = textInput.value.trim().split(/\s+/).filter(Boolean);
    wordCount.textContent = `${words.length} words`;
  });

  /* ----------- PASTE BTN ----------- */
  pasteBtn.addEventListener("click", async () => {
    try {
      const text = await navigator.clipboard.readText();
      textInput.value = text;
      const words = text.trim().split(/\s+/).filter(Boolean);
      wordCount.textContent = `${words.length} words`;
    } catch (err) {
      alert("Unable to read clipboard (browser permission denied).");
    }
  });

  /* ----------- CLEAR BTN ----------- */
  if (clearBtn) {
    clearBtn.addEventListener("click", () => {
      textInput.value = "";
      summaryBox.innerHTML = `<p class="placeholder">Summary result……</p>`;
      wordCount.textContent = "0 words";
    });
  }

  /* ----------- SUMMARIZE ----------- */
  summarizeBtn.addEventListener("click", async () => {
    const text = textInput.value.trim();
    if (!text) {
      summaryBox.innerHTML = `<p class="placeholder">Please paste or type some text first.</p>`;
      return;
    }

    // disable controls while working
    summarizeBtn.disabled = true;
    summarizeBtn.textContent = "Summarizing...";
    pasteBtn.disabled = true;
    if (clearBtn) clearBtn.disabled = true;
    summaryBox.innerHTML = `<p class="placeholder">Processing...</p>`;

    const formData = new FormData();
    formData.append("text", text);
    formData.append("ratio", selectedLength);
    // send 'keypoints' to backend when user selected 'bullet'
    formData.append("mode", selectedMode === "bullet" ? "keypoints" : selectedMode);

    try {
      const res = await fetch("summarize.php", {
        method: "POST",
        body: formData
      });

      // If server responds not-ok show the body as message (helps debugging)
      const bodyText = await res.text();
      if (!res.ok) {
        summaryBox.innerHTML = `<p class="placeholder">Server error: ${res.status} ${res.statusText}<br>${escapeHtml(bodyText)}</p>`;
      } else {
        // For bullet mode the server returns HTML with <br> (nl2br) — show it raw
        summaryBox.innerHTML = bodyText.trim() ? bodyText : `<p class="placeholder">No summary returned.</p>`;
      }
    } catch (err) {
      summaryBox.innerHTML = `<p class="placeholder">Error connecting to server: ${err.message}</p>`;
    } finally {
      summarizeBtn.disabled = false;
      summarizeBtn.textContent = "Summarize";
      pasteBtn.disabled = false;
      if (clearBtn) clearBtn.disabled = false;
    }
  });

  /* ----------- COPY BUTTON ----------- */
  copyBtn.addEventListener("click", async () => {
    try {
      // use innerText so HTML tags are not copied
      await navigator.clipboard.writeText(summaryBox.innerText.trim());
      copyBtn.textContent = "Copied!";
      setTimeout(() => (copyBtn.textContent = "Copy Text"), 1200);
    } catch (err) {
      alert("Unable to copy (clipboard permission denied).");
    }
  });

  /* ----------- DARK MODE ----------- */
  themeToggle.addEventListener("click", () => {
    document.body.classList.toggle("dark");
    themeToggle.textContent =
      document.body.classList.contains("dark") ? "☀️" : "🌙";
  });

  /* small helper to escape HTML for safe display when showing errors */
  function escapeHtml(s) {
    return s
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }
});
