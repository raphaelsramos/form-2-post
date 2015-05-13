README

Want to save the data sended by your WPCF7 forms? That's the goal of this plugin. It's create a Custom Post Type **form2post** for save the data.

The email sended is saved as *post content* and all the form fields are saved as metadata. The files sended as attachment are included in the Media Library and the post content is updated to have the attachment's links.

### Shortcode

You can use the shortcode **[f2p form_id="{int}" height="{int}" html_id="{string}" html_class="{string}" include_css="{boolean}"]** to display to list of emails sent through the form and its data.

* **form_id (required):** the ID of **WPCF7 form** that you want to show the data
* **height (optional):** set the HEIGHT of the post list and content area
* **html_id (optional):** the ID attribute of the data list
* **html_class (optional):** the CLASS attribute of the data list
* **include_css (optional):** the plugins comes, by default, with a **f2p.css** file, that set the default layout of the data list, but it does'nt is included by default in your page, only if you set the **include_css* variable as true;

### TO-DO

* Shortcode to export list data as JSON