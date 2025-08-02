# GPL v2 Compliance Checklist — Try-On Tool

This checklist must be completed for every public release (stable or beta) of the Try-On Tool plugin.

---

## 📦 Packaging

- [ ] `COPYING` file (GPL-2.0-only full license text) is present at root
- [ ] All `.php`, `.js`, `.css` files have correct GPL license header
- [ ] `WRITTEN_OFFER.txt` included at root
- [ ] `README.md` and `INSTALL.md` updated for this release
- [ ] All source files are included (no minified-only code without originals)
- [ ] `composer.json` and `composer.lock` are included (if applicable)

---

## ⚖️ Legal / Compatibility

- [ ] All bundled third-party code is GPL-compatible
- [ ] `DEPENDENCIES.md` reflects any new libraries or version changes
- [ ] No terms of use, EULAs, or field-of-use restrictions imposed anywhere
- [ ] Admin UI includes GPL no-warranty notice

---

## 📁 Source Code Access

- [ ] GitHub repository is publicly accessible at: `https://github.com/sulemanaslam1101/Try-OnTool`
- [ ] Repository contains complete source code for this release
- [ ] Release tags are properly created and documented
- [ ] `WRITTEN_OFFER.txt` points to GitHub repository for source code access
- [ ] Repository remains accessible for **3 years** minimum

---

## 🧩 Optional QA (Recommended)

- [ ] Run `reuse lint` to check license headers (https://reuse.software)
- [ ] Check file diff for any unlicensed new files
- [ ] Validate plugin loads and activates on clean WP + WC install

---

## ✍️ Prepared by: Ibrahim Ghulam  
## 📅 Release Date: 01/08/2025
