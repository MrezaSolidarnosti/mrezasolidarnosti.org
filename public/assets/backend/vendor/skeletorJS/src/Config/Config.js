class Config {
    constructor(initialConfig = {}) {
        this.config = new Map(Object.entries(initialConfig));
    }
    set(key, value) {
        this.config.set(key, value);
    }

    get(key) {
        return this.config.has(key) ? this.config.get(key) : null;
    }

    remove(key) {
        this.config.delete(key);
    }

    has(key) {
        return this.config.has(key);
    }

    keys() {
        return Array.from(this.config.keys());
    }

    values() {
        return Array.from(this.config.values());
    }

    clear() {
        this.config.clear();
    }

    toObject() {
        return Object.fromEntries(this.config);
    }
}


export default (new Config());