# ProgressBar Class

The ProgressBar class is a simple utility for creating and managing progress bars in JavaScript.

## Usage

```javascript
const bar = new ProgressBar();
```

## Methods
`start(continer, duration, prepend)` - Start the progress bar. The `container` parameter is the element to which the progress bar will be appended. The `duration` parameter is the time in seconds that the progress bar will take to complete. The `prepend` parameter is a boolean that determines whether the progress bar will be prepended to the container or appended. The default value is `false`.
By default, the duration is set to 1.5s and acts as a fake progress bar. If you want to use a real progress bar, set the duration to false and use the `setProgress()` method to update the progress.

`setProgress(progress)` - Set the progress of the bar. The `progress` parameter is a number between 0 and 100 that represents the percentage of the progress bar that is filled.

`stop()` - Stop the progress bar and remove it from the container.