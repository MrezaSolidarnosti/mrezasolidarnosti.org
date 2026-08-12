export default class LocalStorage {
    static set(key, value, jsonStringifyValue = false) {
        if(!jsonStringifyValue) {
            localStorage.setItem(key, value);
            return;
        }
        localStorage.setItem(key, JSON.stringify(value));
    }

    static get(key, parseJson = false) {
        let result = localStorage.getItem(key);
        if(result) {
            if (!parseJson) {
                return result;
            }
            try {
                return JSON.parse(result);
            } catch (e) {
                console.error(e);
                throw new Error(`Error while parsing value from LocalStorage with key: ${key} - ${e}`);
            }
        }
        return null;
    }

    static remove(key) {
        localStorage.removeItem(key);
    }
}