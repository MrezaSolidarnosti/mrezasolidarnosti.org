export default class SaveResponse {

    success;
    messages;
    constructor({success, messages = []}) {
        this.success = success;
        this.messages = messages;
    }
}