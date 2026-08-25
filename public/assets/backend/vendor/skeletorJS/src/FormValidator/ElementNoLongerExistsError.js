export default class ElementNoLongerExistsError extends Error {
  constructor(message) {
    super(message);
    this.name = "ElementNoLongerExistsError";
  }
}