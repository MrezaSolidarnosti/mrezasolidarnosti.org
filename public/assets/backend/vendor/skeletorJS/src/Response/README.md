# Response Class

This is a class representing a response. It provides methods to extract different types of errors and messages from the response.

## Constructor

### `Response(response: Object)`

- `response` {Object}: The response object from the API.

## Methods

`getErrors(): Array | null`

Returns an array of errors if any, otherwise returns null.

`getGeneralErrors(): Array | null`

Returns an array of general errors if any, otherwise returns null.

`getErrorMessages(): Array`

Returns an array of error messages extracted from individual error objects.

`getGeneralErrorMessages(): Array`

Returns an array of error messages extracted from individual general error objects.

`getCSRFTokenInput(): string`

Returns the CSRF token from the response.

`getMessage(): string | null`

Returns the message from the response, or null if not available.

`getStatus(): boolean`

Returns 1 if the response status is true, otherwise returns 0.

`getData(): Object`

Returns the data object from the response.



## Example Usage

```javascript

const resData = {
    errors: [{ message: 'Error 1' }, { message: 'Error 2' }],
    generalErrors: [{ message: 'General Error 1' }, { message: 'General Error 2' }],
    token: 'some-csrf-token',
    message: 'Some success message',
    status: true
};

const response = new Response(resData);

console.log(response.getErrors()); // Output: [{ message: 'Error 1' }, { message: 'Error 2' }]
console.log(response.getGeneralErrors()); // Output: [{ message: 'General Error 1' }, { message: 'General Error 2' }]
console.log(response.getErrorMessages()); // Output: ['Error 1', 'Error 2']
console.log(response.getGeneralErrorMessages()); // Output: ['General Error 1', 'General Error 2']
console.log(response.getCSRFTokenInput()); // Output: 'some-csrf-token'
console.log(response.getMessage()); // Output: 'Some success message'
console.log(response.getStatus()); // Output: true

```