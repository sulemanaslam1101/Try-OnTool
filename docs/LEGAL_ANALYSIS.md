# Legal Analysis — GPL Applicability for Try-On Tool

---

## ✅ Summary

Try-On Tool is distributed under the **GNU General Public License v2 only (GPL-2.0-only)**. This document explains the legal basis for that decision and outlines our compliance obligations.

---

## 📌 Why GPL-2.0 Applies

Try-On Tool is a plugin for WordPress and WooCommerce, which are both licensed under GPL-2.0. According to long-standing legal interpretations (see below), WordPress plugins are considered **derivative works** of WordPress and therefore **must also be GPL-compatible**.

### Supporting Points:

- Try-On Tool links directly to WooCommerce and WordPress core APIs (both GPL-2.0)
- Plugin integrates via hooks, filters, and template overrides
- Follows guidance from WordPress.org Plugin Handbook and FSF position

➡ Therefore, the plugin must be licensed under GPL-2.0 (we chose “only” rather than “or later” to retain strict control).

---

## 🛡 License Declaration

Try-On Tool is licensed under:

> GNU General Public License v2 only (GPL-2.0-only)  
> Copyright © 2025 DataDove LTD

The full license text is provided in `COPYING`.

---

## 📦 Compliance Steps Taken

| Area | Compliance Detail |
|------|-------------------|
| `COPYING` | Full license text included at root |
| File headers | All source files include standard GPL-2.0 header |
| WRITTEN_OFFER.txt | Provided for §3(b) fallback |
| Source packaging | All source is bundled with each release |
| No-warranty splash | Displayed in Admin UI and JS console |
| README / INSTALL | Provided with usage + environment details |
| Third-party licenses | Documented in `DEPENDENCIES.md` |
| No extra restrictions | No EULAs, NDAs, or anti-reverse clauses present |
| Contact | gplqueries@tryontool.com maintained as support point |

---

## 📚 References

- https://www.gnu.org/licenses/gpl-2.0.html
- https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#1-plugins-must-be-compatible-with-the-gpl
- https://www.gnu.org/licenses/gpl-faq.en.html#GPLModuleLicense
- https://www.gnu.org/licenses/gpl-faq.en.html#WordPressPlugins

---

Prepared by: DataDove LTD  
Date: 2025  
Contact: [gplqueries@tryontool.com](mailto:gplqueries@tryontool.com)
