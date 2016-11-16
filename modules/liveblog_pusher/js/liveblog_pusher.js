(function(Drupal, drupalSettings) {
  Drupal.behaviors.liveblogPusher = {
    attach: function(context) {
      Drupal.behaviors.liveblogStream.getContainer(context)
        .once('liveblog-pusher-initialised')
        .each(function(i, element) {
          // Enable pusher logging - don't include this in production
          // TODO: add a setting for "Debugging mode"
          Pusher.logToConsole = true;

          var pusher = new Pusher(drupalSettings.liveblog_pusher.key, {
            encrypted: true
          });
          var liveblogStream = Drupal.behaviors.liveblogStream.getInstance(context)

          var channel = pusher.subscribe(drupalSettings.liveblog_pusher.channel);
          channel.bind('add', function(data) {
            liveblogStream.addPost(data)
          });
          channel.bind('edit', function(data) {
            liveblogStream.editPost(data)
          })
        })
    }
  }

})(Drupal, drupalSettings)