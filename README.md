# GTAW Furniture Catalog

> **🌐 [Visit the Application →](https://gtawfur.nctrn.cc/)**

A web application for GTA World players to browse, search, and favorite furniture items from the server's mapping system.

---

## ✨ About

This is a community project I built to help GTA World players find furniture more easily. Instead of guessing prop names or searching through forums, you can:

- **Fast Browsing** — Category and tag filters for quick discovery
- **Instant Search** — Find furniture by name
- **One-Click Copy** — Copy `/sf [name]` commands to clipboard
- **Personal Favorites** — Save items via GTA World OAuth login
- **Mobile Responsive** — Works on any device

### Features

- **Rich filters** — Categories plus grouped tag filters (style, mood, size, materials, effects, themes)
- **Image lightbox** — Click any image to open a zoomed preview with navigation and shareable links
- **Keyboard shortcuts** — `/` to focus search, arrow keys to navigate, `C` to copy `/sf` and `F` to favorite
- **Favorites export** — Export all your saved items as a ready-to-use list of `/sf` commands (clipboard or text file)
- **Admin Panel** — Manage furniture, categories, tags, users, and imports via a web UI. [View screenshots](https://ibb.co/album/vwMwRK).

The application is hosted at **[https://gtawfur.nctrn.cc/](https://gtawfur.nctrn.cc/)** — this is the only supported instance (for now).

---

## 🎯 Why Open Source?

I've made the source code publicly available for transparency and community feedback:

- **🔍 Transparency** — The community can see exactly how the application works and what data it stores
- **🔒 Security** — Public code allows for security review
- **🤝 Community Trust** — Open development builds confidence in how user data is handled
- **💡 Feedback** — Users can report bugs and suggest improvements
- **📚 Learning** — Developers can explore the implementation

This repository is published for transparency purposes. The code is available for review and learning, but the primary way to use the application is through the hosted version.

---

## 👥 For Users

### Using the Application

Visit **[https://gtawfur.nctrn.cc/](https://gtawfur.nctrn.cc/)** to:
- Browse furniture items by category
- Search for specific items
- Save your favorite furniture (requires GTA World login)
- Copy spawn commands directly to your clipboard

### Reporting Issues

Your feedback helps make this better. If you encounter a problem or have a suggestion:

- **🐛 Bug Reports** — [Open an issue](https://github.com/Botticena/GTAW-Fur/issues/new?template=bug_report.md) describing what went wrong
- **💡 Feature Requests** — [Share your idea](https://github.com/Botticena/GTAW-Fur/issues/new?template=feature_request.md) for how to improve the app

Please include relevant details like what you were doing, what happened, and your browser/device information.

For security concerns, please use [GitHub Security Advisories](https://github.com/Botticena/GTAW-Fur/security/advisories/new) instead of opening a public issue.

---

## 🔒 Privacy & Security

Privacy is a priority. This application is designed to collect minimal data:

**Data Stored:**
- GTA World username (from OAuth login)
- Main character name (if available from OAuth)
- Favorite furniture items

**Data NOT Stored:**
- Email addresses
- Passwords (handled by GTA World OAuth)
- IP addresses
- Any other personal information

The application follows GTA World's website regulations and uses only their official OAuth system for authentication. Standard security practices are implemented (SQL injection prevention, XSS protection, CSRF tokens, secure sessions) — all visible in the source code for verification.

---

## 🛠️ For Developers

Built with PHP, MySQL, and vanilla JavaScript — no frameworks or external dependencies.

The codebase is structured simply: backend logic in `includes/`, frontend JavaScript in `js/`, and templates in `templates/`. If you're interested in the implementation, feel free to explore the code. Comments are included throughout to explain the functionality.

This repository is primarily for transparency and reference. For installation and deployment details, see the code structure and comments in the files themselves.

---

## ⚠️ Disclaimer

This is a community project and is **not affiliated with or endorsed by GTA World**. It is an independent project built to enhance the GTA World experience.

---

## 🙏 Credits

Made with ❤️ by [Lena](https://forum.gta.world/en/profile/56418-lena/) for the GTA World Community

Questions or suggestions? Please open an issue or reach out.

---

**🔗 [Visit the Application →](https://gtawfur.nctrn.cc/)**
