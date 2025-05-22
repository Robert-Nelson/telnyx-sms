CREATE TABLE IF NOT EXISTS TelnyxMessageProfile (
    id INTEGER PRIMARY KEY,
    value TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS TelnyxMessageDirection (
    id INTEGER PRIMARY KEY,
    value TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS TelnyxCarrier (
    id INTEGER PRIMARY KEY,
    value TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS TelnyxLineType (
    id INTEGER PRIMARY KEY,
    value TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS TelnyxEndpoint (
    id INTEGER PRIMARY KEY,
    phone_number TEXT UNIQUE,
    carrier_id INTEGER,
    line_type_id INTEGER,
    INDEX (carrier_id),
    INDEX (line_type_id),
    CONSTRAINT FOREIGN KEY (carrier_id) REFERENCES TelnyxCarrier (id)
      ON DELETE RESTRICT,
    CONSTRAINT FOREIGN KEY (line_type_id) REFERENCES TelnyxLineType (id)
      ON DELETE RESTRICT);
CREATE TABLE IF NOT EXISTS TelnyxMessage (
    id INTEGER PRIMARY KEY,
    profile_id INTEGER NOT NULL,
    message_id INTEGER NOT NULL UNIQUE,
    direction_id INTEGER NOT NULL,
    cost_amount REAL,
    cost_currency TEXT,
    received_time INTEGER,
    sent_time INTEGER,
    completed_time INTEGER,
    body_text TEXT,
    INDEX (profile_id),
    INDEX (direction_id),
    CONSTRAINT FOREIGN KEY (profile_id) REFERENCES TelnyxMessageProfile (id)
      ON DELETE RESTRICT,
    CONSTRAINT FOREIGN KEY (direction_id) REFERENCES TelnyxMessageDirection (id)
      ON DELETE RESTRICT);
CREATE TABLE IF NOT EXISTS TelnyxEndpointUsage (
    id INTEGER PRIMARY KEY,
    value TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS TelnyxEndpointMap (
    id INTEGER PRIMARY KEY,
    usage_id INTEGER,
    message_id INTEGER,
    endpoint_id INTEGER,
    delivery_status TEXT,
    INDEX (usage_id),
    INDEX (message_id),
    INDEX (endpoint_id),
    CONSTRAINT FOREIGN KEY (usage_id) REFERENCES TelnyxEndpointUsage (id)
      ON DELETE RESTRICT,
    CONSTRAINT FOREIGN KEY (message_id) REFERENCES TelnyxMessage (id)
      ON DELETE RESTRICT,
    CONSTRAINT FOREIGN KEY (endpoint_id) REFERENCES TelnyxEndpoint (id)
      ON DELETE RESTRICT,
    CONSTRAINT unique_endpoint UNIQUE (usage_id, message_id, endpoint_id));
CREATE TABLE IF NOT EXISTS TelnyxMessageError (
    id INTEGER PRIMARY KEY,
    message_id INTEGER,
    error_text TEXT,
    INDEX (message_id),
    CONSTRAINT FOREIGN KEY (message_id) REFERENCES TelnyxMessage (id)
      ON DELETE RESTRICT);
