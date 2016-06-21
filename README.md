# Send feedback - Contact Plugin

Plugin for basic contact form functionalities. Supports most common fields, supports
storing in database and uploading attachments.

This plugin is compatible with Newscoop 4.3 and higher.

Features
--------

- Send feedback messages (supported fields: first name, last name, email, subject and message)
- Supports posting via AJAX and normal post with custom redirect page
- Attachments handling (only images and pdf documents), attach to mail or upload into Newscoop Media Library
- Let site visitors decide who to send email to (with spam protection)
- Store feedback in database and view in Newscoop backend (see detailed instructions)

Installation
-------------
Installation is a quick process:


1. How to install this plugin?
2. That's all!

### Step 1: How to install this plugin?
Run the command:
``` bash
$ php application/console plugins:install "newscoop/send-feedback-plugin"
$ php application/console assets:install public/
```
Plugin will be installed to your project's `newscoop/plugins/Newscoop` directory.

### Step 2: That's all!
Go to Newscoop Admin panel and then open `Plugins` tab. The Plugin will show up there. You can now use the plugin.


**Note:**

To update this plugin run the command:
``` bash
$ php application/console plugins:update "newscoop/send-feedback-plugin"
$ php application/console assets:install public/
```

To remove this plugin run the command:
``` bash
$ php application/console plugins:remove "newscoop/send-feedback-plugin"
```

Documentation
-------------

### Extended plugin documentation

For more information please see our [wiki page](https://wiki.sourcefabric.org/display/NPS/Send+Feedback+-+Contact+plugin).

### Read plugin settings

With the help of the tag {{ get_feedback_settings }} it's possible to read the plugin settings in the frontend. All current keys which can be read are listed below as well.

```
{{ get_feedback_settings }}
{{ get_feedback_settings assign="mySettingVariable" }}
{{ $mySettingVariable.to }} // Email addresses of valid receivers
{{ $mySettingVariable.storeInDatabase }} // Whether the feedback will be stored in the database
{{ $mySettingVariable.allowAttachments }} // Whether attachments are allowed
{{ $mySettingVariable.allowAnonymous }} // Whether non-registered users can send feedback
```

### Basic example

``` html
<form id="sendFeedbackForm" method="post" action="/plugin/send-feedback" enctype="multipart/form-data">
    <div class="form-group">
        <label for="sendFeedbackForm_first_name">First Name</label>
        <input type="text" id="sendFeedbackForm_first_name" name="sendFeedbackForm[first_name]" required="required" class="form-control" placeholder="First name">
    </div>
    <div class="form-group">
        <label for="sendFeedbackForm_last_name">Last Name</label>
        <input type="text" id="sendFeedbackForm_last_name" name="sendFeedbackForm[last_name]" required="required" class="form-control" placeholder="Last name">
    </div>
    <div class="form-group">
        <label for="sendFeedbackForm_email">Email</label>
        <input type="text" id="sendFeedbackForm_email" name="sendFeedbackForm[email]" required="required" class="form-control" placeholder="Email">
    </div>
    <div class="form-group">
        <label for="sendFeedbackForm_subject">Subject</label>
        <input type="text" id="sendFeedbackForm_subject" name="sendFeedbackForm[subject]" required="required" class="form-control" placeholder="Subject">
    </div>
    <div class="form-group">
        <label for="sendFeedbackForm_message">Message</label>
        <textarea id="sendFeedbackForm_message" name="sendFeedbackForm[message]" required="required" class="form-control" placeholder="Message"></textarea>
    </div>

    <input id="submitSendFeedbackForm" class="btn btn-primary" type="submit" value="Submit">
</form>
```

License
-------

This bundle is under the GNU General Public License v3. See the complete license in the bundle:

    LICENSE

About
-------
This Bundle is a [Sourcefabric z.ú.](https://github.com/sourcefabric) initiative.

