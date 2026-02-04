# WP Internal Link Automator

A lightweight WordPress plugin that automates internal linking structure to improve SEO authority flow (PageRank) without over-optimizing.

## 🎯 The SEO Problem
Internal linking is critical for distributing link equity and helping Google crawl a site. However, manual linking is slow, and inconsistent. Over-linking (linking the same keyword 10 times in one post) can be seen as "keyword stuffing" by search engines.

## 💡 The Solution
This plugin parses post content and automatically links targeted keywords to specific destination URLs. 

**Key SEO Features:**
* **Frequency Capping:** Uses a `limit=1` regex logic to only link the *first* occurrence of a keyword per post. This ensures natural-looking density.
* **Word Boundaries:** Uses Regex `\b` boundaries to ensure "Cat" doesn't trigger a link inside "Catastrophe".
* **Performance:** Hooks into `the_content` only on singular views to minimize database impact.

## 🛠 Installation & Usage
1. Download the repo as a ZIP.
2. Upload to WordPress via **Plugins > Add New**.
3. Left Menu Locate: SEO Linker.
4. Add your keywords in the format: `Keyword|URL`

**Example:**
```text
SEO Audit|https://example.com/services/audit
Technical SEO|https://example.com/blog/technical-seo-guide
```
