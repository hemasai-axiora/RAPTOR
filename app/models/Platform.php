<?php
// Raptor CRM Platform Model

class Platform extends Model {
    // Get all platforms
    public function getPlatforms() {
        $this->query('SELECT * FROM platforms ORDER BY name ASC');
        return $this->resultSet();
    }

    // Get platform by ID
    public function getPlatformById($id) {
        $this->query('SELECT * FROM platforms WHERE platform_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    // Get platform by name
    public function getPlatformByName($name) {
        $this->query('SELECT * FROM platforms WHERE LOWER(name) = LOWER(:name)');
        $this->bind(':name', $name);
        return $this->single();
    }

    // Add platform
    public function addPlatform($data) {
        $this->query('INSERT INTO platforms (name, icon) VALUES (:name, :icon)');
        $this->bind(':name', $data['name']);
        $this->bind(':icon', $data['icon']);
        return $this->execute();
    }

    // Update platform
    public function updatePlatform($data) {
        $this->query('UPDATE platforms SET name = :name, icon = :icon WHERE platform_id = :id');
        $this->bind(':name', $data['name']);
        $this->bind(':icon', $data['icon']);
        $this->bind(':id', $data['platform_id']);
        return $this->execute();
    }

    // Remove platform
    public function removePlatform($id) {
        $this->query('DELETE FROM platforms WHERE platform_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }
}
