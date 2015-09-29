# Send feedback - Contact Plugin

Plugin for basic contact form functionalities. Supports most common fields, supports 
storing in database and uploading attachments.

This plugin is compatbible with Newscoop 4.3 and higher.

### Featrures

- Send feedback messages (supported fields: first name, last name, email, subject and message) 
- Supports posting via AJAX and normal post with custom redirect page
- Attachments handling (only images and pdf documents), attach to mail or upload into Newscoop Media Library
- Let site visitors decide who to send email to (with spam protection)
- Store feedback in database and view in Newscoop backend (see detailed instructions)

### Extended plugin documentation

For more information please see our [wiki page](https://wiki.sourcefabric.org/display/NPS/Send+Feedback+-+Contact+plugin).

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

### Commands
#### Install the plugin

``` bash
$ php application/console plugins:install "newscoop/send-feedback-plugin" --env=prod
$ php application/console assets:install public/
```

#### Update the plugin

``` bash
$ php application/console plugins:update "newscoop/send-feedback-plugin" --env=prod
```

#### Remove the plugin

``` bash
$ php application/console plugins:remove "newscoop/send-feedback-plugin" --env=prod
```
