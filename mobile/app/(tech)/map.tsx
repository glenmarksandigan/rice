import React, { useState, useCallback } from 'react';
import { View, Text, StyleSheet, ActivityIndicator, Dimensions, Image } from 'react-native';
import MapView, { Marker, Callout, PROVIDER_GOOGLE } from 'react-native-maps';
import { useFocusEffect } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, SPACING, RADIUS, SHADOWS } from '../../constants/theme';
import { reportService } from '../../services/api.service';
import { API_BASE_URL } from '../../services/api.config';

const { width, height } = Dimensions.get('window');

// Default center: Philippines/Bohol context
const INITIAL_REGION = {
    latitude: 9.8510,
    longitude: 124.1435,
    latitudeDelta: 0.5,
    longitudeDelta: 0.5,
};

export default function TechMapScreen() {
    const [reports, setReports] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);

    const loadReports = async () => {
        try {
            const data = await reportService.getReports({});
            // Filter only reports with valid coordinates
            const mapped = Array.isArray(data)
                ? data.filter(r => r.latitude && r.longitude && !isNaN(parseFloat(r.latitude)))
                : [];
            setReports(mapped);
        } catch (err) {
            console.error('Failed to load reports for map:', err);
        } finally {
            setLoading(false);
        }
    };

    useFocusEffect(useCallback(() => {
        loadReports();
    }, []));

    if (loading) {
        return (
            <View style={styles.centered}>
                <ActivityIndicator size="large" color={COLORS.primary} />
                <Text style={styles.loadingText}>Loading Map...</Text>
            </View>
        );
    }

    return (
        <View style={styles.container}>
            <MapView
                style={styles.map}
                initialRegion={INITIAL_REGION}
                provider={PROVIDER_GOOGLE}
            >
                {reports.map((report) => (
                    <Marker
                        key={report.report_id}
                        coordinate={{
                            latitude: parseFloat(report.latitude),
                            longitude: parseFloat(report.longitude),
                        }}
                        pinColor={report.status === 'Pending' ? COLORS.statusPending : COLORS.primary}
                    >
                        <Callout tooltip>
                            <View style={styles.calloutContainer}>
                                <View style={styles.calloutHeader}>
                                    <Text style={styles.calloutTitle}>Report #{report.report_id}</Text>
                                    <View style={[styles.badge, { backgroundColor: report.status === 'Pending' ? COLORS.statusPending : COLORS.statusVerified }]}>
                                        <Text style={styles.badgeText}>{report.status}</Text>
                                    </View>
                                </View>

                                <Text style={styles.calloutSub}>Farmer: {report.fname} {report.lname}</Text>
                                <Text style={styles.calloutDate}>{new Date(report.reported_at).toLocaleDateString()}</Text>

                                {report.images && report.images.length > 0 && (
                                    <View style={styles.imagePreviewWrap}>
                                        <Image
                                            source={{ uri: `${API_BASE_URL}/${report.images[0]}` }}
                                            style={styles.calloutImage}
                                            resizeMode="cover"
                                        />
                                    </View>
                                )}

                                <Text style={styles.calloutLocation} numberOfLines={1}>
                                    📍 {report.field_location || 'Coordinates only'}
                                </Text>
                            </View>
                            <View style={styles.calloutArrow} />
                        </Callout>
                    </Marker>
                ))}
            </MapView>

            <View style={styles.floatingHeader}>
                <Text style={styles.headerTitle}>Field Visitation Map</Text>
                <Text style={styles.headerSub}>{reports.length} reports mapped</Text>
            </View>

            <View style={styles.legend}>
                <View style={styles.legendItem}>
                    <View style={[styles.legendDot, { backgroundColor: COLORS.statusPending }]} />
                    <Text style={styles.legendText}>Pending</Text>
                </View>
                <View style={styles.legendItem}>
                    <View style={[styles.legendDot, { backgroundColor: COLORS.primary }]} />
                    <Text style={styles.legendText}>Verified</Text>
                </View>
            </View>
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1 },
    map: { width, height },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.background },
    loadingText: { marginTop: 12, color: COLORS.textMuted, fontSize: 14 },

    floatingHeader: {
        position: 'absolute', top: 60, left: 20, right: 20,
        backgroundColor: 'rgba(255,255,255,0.9)', borderRadius: RADIUS.lg,
        padding: SPACING.md, ...SHADOWS.medium,
    },
    headerTitle: { fontSize: 18, fontWeight: '800', color: COLORS.textPrimary },
    headerSub: { fontSize: 12, color: COLORS.textMuted, marginTop: 2 },

    legend: {
        position: 'absolute', bottom: 20, left: 20,
        backgroundColor: 'rgba(255,255,255,0.9)', borderRadius: RADIUS.md,
        padding: SPACING.sm, ...SHADOWS.small,
    },
    legendItem: { flexDirection: 'row', alignItems: 'center', marginVertical: 2 },
    legendDot: { width: 10, height: 10, borderRadius: 5, marginRight: 8 },
    legendText: { fontSize: 11, fontWeight: '600', color: COLORS.textPrimary },

    calloutContainer: {
        width: 200, backgroundColor: COLORS.white, borderRadius: RADIUS.md,
        padding: SPACING.md, ...SHADOWS.medium, borderBottomWidth: 0,
    },
    calloutHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 },
    calloutTitle: { fontSize: 14, fontWeight: '800', color: COLORS.textPrimary },
    calloutSub: { fontSize: 12, color: COLORS.textSecondary, fontWeight: '600' },
    calloutDate: { fontSize: 10, color: COLORS.textMuted, marginBottom: 6 },
    calloutLocation: { fontSize: 11, color: COLORS.primary, marginTop: 6, fontStyle: 'italic' },
    imagePreviewWrap: { width: '100%', height: 80, borderRadius: RADIUS.sm, overflow: 'hidden', marginVertical: 4 },
    calloutImage: { width: '100%', height: 80 },

    badge: { paddingHorizontal: 6, paddingVertical: 2, borderRadius: 10 },
    badgeText: { fontSize: 9, fontWeight: '800', color: COLORS.white },
    calloutArrow: {
        width: 0, height: 0, backgroundColor: 'transparent',
        borderStyle: 'solid', borderLeftWidth: 10, borderRightWidth: 10,
        borderTopWidth: 10, borderLeftColor: 'transparent',
        borderRightColor: 'transparent', borderTopColor: COLORS.white,
        alignSelf: 'center', marginTop: -1,
    },
});
