# FluentCRM Events

This is a [FluentCRM](https://wpfusion.com/go/fluentcrm) module which adds an activity / events feed to the contact sidebar, similar to ActiveCampaign or Bento.

It's designed to make FluentCRM compatible with WP Fusion's [Event Tracking Addon](https://wpfusion.com/documentation/event-tracking/event-tracking-overview/).

![image](https://github.com/verygoodplugins/fluent-crm-events/assets/13076544/678ba9c7-ee5f-4e20-af49-10d55289f429)


## Creating events

Events can be created via PHP or via the REST API.

#### Creating events via PHP

Call the `fcrm_events_add_event()` function with the following parameters:

* `$subscriber_email_or_id`: The email address or contact ID of the subscriber you'd like to create an event for.
* `$event_type`: The event type (controls the icon displayed with the event). Defaults to `wp_fusion`.
* `$event_title`: A text description of the event.
* `$event_value`: (optional) An associative array containing any number of event properties and values.
* `$timestamp`: (optional) The time the event occurred.

For example to track course progress in LearnDash:

```php
function track_progress( $data ) {

	$values = array(
		'Course Title' => get_the_title( $data['course']->ID ),
		'Last Step'    => get_the_title( $data['progress']['last_id'] ),
	);

	$user = wp_get_current_user();

	fcrm_events_add_event( $user->user_email, 'course_progress', 'Course Progressed', $values );

}

add_action( 'learndash_course_completed', 'track_progress' );
add_action( 'learndash_lesson_completed', 'track_progress' );
add_action( 'learndash_topic_completed', 'track_progress' );
```

#### Creating events via the REST API

Events can be POSTed to `/wp-json/fluent-crm/v2/events`. The body should be a JSON-encoded object, like

```json
{
  "email": "subscriber@verygoodplugins.com",
  "title": "Quiz Completed",
  "type": "wp_fusion",
  "value": {
    "grade": "1",
    "percentage": "85"
  }
}

```

## Wish list

- [ ] Use events as triggers and conditions in automations

--------------------

## Changelog

### 1.0.1 on December 13th, 2023
- Tracked events will now fire a `fluent_crm/track_activity_by_subscriber` action so that the subscriber's Last Activity is updated

### 1.0.0 on November 17th, 2023

- Initial release

--------------------

## Installation

1. Download the [latest tagged release](https://github.com/verygoodplugins/fluent-crm-events/tags).

2. Navigate to Plugins » Add New in the WordPress admin and upload the file.

3. Activate the plugin.

4. Create an event either via PHP, the REST API, or using the [WP Fusion Event Tracking Addon](https://wpfusion.com/documentation/event-tracking/event-tracking-overview/).
