const API_URL = process.env.NEXT_PUBLIC_API_URL;

export async function getAssessments() {
    const response = await fetch(`${API_URL}/assessments`);
    if (!response.ok) throw new Error('Failed to fetch assessments');
    return response.json();
}

export async function getAssessment(id) {
    const response = await fetch(`${API_URL}/assessments/${id}`);
    if (!response.ok) throw new Error('Failed to fetch assessment');
    return response.json();
}

export async function createAssessment(data) {
    const response = await fetch(`${API_URL}/assessments`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(data),
    });
    if (!response.ok) throw new Error('Failed to create assessment');
    return response.json();
}

export async function updateAssessment(id, data) {
    const response = await fetch(`${API_URL}/assessments/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(data),
    });
    if (!response.ok) throw new Error('Failed to update assessment');
    return response.json();
}

export async function deleteAssessment(id) {
    const response = await fetch(`${API_URL}/assessments/${id}`, {
        method: 'DELETE',
    });
    if (!response.ok) throw new Error('Failed to delete assessment');
    return response.json();
}