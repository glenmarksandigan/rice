import React, { useState, useCallback, useRef, useEffect } from 'react';
import {
    View, Text, StyleSheet, FlatList, TouchableOpacity, RefreshControl,
    Modal, TextInput, ScrollView, Alert, ActivityIndicator, Animated,
} from 'react-native';
import { useFocusEffect } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, SPACING, RADIUS, SHADOWS, STATUS_CONFIG } from '../../constants/theme';
import { useAuth } from '../../contexts/AuthContext';
import { useLanguage } from '../../contexts/LanguageContext';
import { claimService, reportService } from '../../services/api.service';
import { useFadeIn, usePressScale } from '../../constants/animations';

const DAMAGE_TYPES = [
    'Bacterial Leaf Blight', 'Rice Blast', 'Brown Spot', 'Sheath Blight',
    'Tungro', 'Rice Grassy Stunt', 'Leaf Scald', 'Narrow Brown Spot', 'Other',
];

const CLAIM_STATUS_STYLES: Record<string, { bg: string; color: string; icon: string }> = {
    Pending: { bg: '#FFF3E0', color: '#E65100', icon: 'time-outline' },
    Verified: { bg: '#E3F2FD', color: '#1565C0', icon: 'checkmark-circle-outline' },
    Paid: { bg: '#F3E5F5', color: '#7B1FA2', icon: 'cash-outline' },
    Rejected: { bg: '#FFEBEE', color: '#C62828', icon: 'close-circle-outline' },
};

// ── Animated Claim Card ─────────────────────────────────
function ClaimCard({ item, st, index }: { item: any; st: any; index: number }) {
    const { scale, onPressIn, onPressOut } = usePressScale(0.97);
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const slideAnim = useRef(new Animated.Value(16)).current;

    useEffect(() => {
        Animated.parallel([
            Animated.timing(fadeAnim, { toValue: 1, duration: 350, delay: index * 70, useNativeDriver: true }),
            Animated.timing(slideAnim, { toValue: 0, duration: 350, delay: index * 70, useNativeDriver: true }),
        ]).start();
    }, []);

    return (
        <Animated.View style={{ opacity: fadeAnim, transform: [{ translateY: slideAnim }, { scale }] }}>
            <TouchableOpacity
                style={styles.claimCard}
                activeOpacity={1}
                onPressIn={onPressIn}
                onPressOut={onPressOut}
            >
                <View style={styles.claimHeader}>
                    <View style={styles.claimIdRow}>
                        <Ionicons name="shield-checkmark" size={18} color={COLORS.primary} />
                        <Text style={styles.claimId}>Claim #{item.claim_id}</Text>
                    </View>
                    <View style={[styles.statusBadge, { backgroundColor: st.bg }]}>
                        <Ionicons name={st.icon as any} size={12} color={st.color} />
                        <Text style={[styles.statusText, { color: st.color }]}>{item.status}</Text>
                    </View>
                </View>

                <View style={styles.claimDetail}>
                    <Ionicons name="document-text-outline" size={14} color={COLORS.textMuted} />
                    <Text style={styles.detailText}>Report #{item.report_id}</Text>
                </View>
                <View style={styles.claimDetail}>
                    <Ionicons name="bug-outline" size={14} color={COLORS.textMuted} />
                    <Text style={styles.detailText}>{item.damage_type}</Text>
                </View>
                {item.field_location && (
                    <View style={styles.claimDetail}>
                        <Ionicons name="location-outline" size={14} color={COLORS.textMuted} />
                        <Text style={styles.detailText} numberOfLines={1}>{item.field_location}</Text>
                    </View>
                )}
                <View style={styles.claimFooter}>
                    <Text style={styles.dateText}>
                        {new Date(item.created_at).toLocaleDateString('en-US', {
                            month: 'short', day: 'numeric', year: 'numeric',
                        })}
                    </Text>
                    {item.rsbsa_num ? <Text style={styles.rsbsaTag}>RSBSA: {item.rsbsa_num}</Text> : null}
                </View>
            </TouchableOpacity>
        </Animated.View>
    );
}

export default function ClaimsScreen() {
    const { user } = useAuth();
    const { t } = useLanguage();
    const [claims, setClaims] = useState<any[]>([]);
    const [verifiedReports, setVerifiedReports] = useState<any[]>([]);
    const [refreshing, setRefreshing] = useState(false);
    const [showForm, setShowForm] = useState(false);
    const [loading, setLoading] = useState(false);

    // Form state
    const [selectedReport, setSelectedReport] = useState<any>(null);
    const [showReportPicker, setShowReportPicker] = useState(false);
    const [damageType, setDamageType] = useState('');
    const [showDamagePicker, setShowDamagePicker] = useState(false);
    const [rsbsaNum, setRsbsaNum] = useState('');
    const [policyNum, setPolicyNum] = useState('');

    // Animations
    const headerFade = useFadeIn(0, 500);
    const { scale: fileBtnScale, onPressIn: fileBtnIn, onPressOut: fileBtnOut } = usePressScale(0.96);

    const loadData = async () => {
        try {
            if (user?.user_id) {
                const [claimsData, reportsData] = await Promise.all([
                    claimService.getClaims({ user_id: user.user_id }),
                    reportService.getReports({ user_id: user.user_id, status: 'Verified' }),
                ]);
                setClaims(Array.isArray(claimsData) ? claimsData : []);
                setVerifiedReports(Array.isArray(reportsData) ? reportsData : []);
            }
        } catch (err) {
            console.log('Failed to load claims:', err);
        }
    };

    useFocusEffect(useCallback(() => { loadData(); }, [user]));

    const onRefresh = async () => { setRefreshing(true); await loadData(); setRefreshing(false); };

    const resetForm = () => { setSelectedReport(null); setDamageType(''); setRsbsaNum(''); setPolicyNum(''); };

    const handleSubmit = async () => {
        if (!selectedReport) { Alert.alert(t('error'), t('selectReportError')); return; }
        if (!damageType) { Alert.alert(t('error'), t('selectDamageError')); return; }

        setLoading(true);
        try {
            const result = await claimService.createClaim({
                user_id: user!.user_id,
                report_id: selectedReport.report_id,
                damage_type: damageType,
                rsbsa_num: rsbsaNum,
                policy_num: policyNum,
            });
            if (result.error) { Alert.alert(t('failed'), result.error); }
            else { Alert.alert(t('success'), t('claimSuccess')); resetForm(); setShowForm(false); await loadData(); }
        } catch (err) {
            Alert.alert(t('error'), t('serverError'));
        } finally {
            setLoading(false);
        }
    };

    const renderClaim = ({ item, index }: { item: any; index: number }) => {
        const st = CLAIM_STATUS_STYLES[item.status] || CLAIM_STATUS_STYLES.Pending;
        return <ClaimCard item={item} st={st} index={index} />;
    };

    return (
        <View style={styles.container}>
            {/* Animated Header */}
            <Animated.View style={headerFade}>
                <LinearGradient colors={[COLORS.gradientStart, COLORS.gradientEnd]} style={styles.header}>
                    <Text style={styles.headerTitle}>🛡️ {t('claims')}</Text>
                    <Text style={styles.headerSub}>
                        {claims.length} {t('claimsFiled')}
                    </Text>
                </LinearGradient>
            </Animated.View>

            {/* File a Claim with press scale */}
            <View style={styles.actionRow}>
                <Animated.View style={{ transform: [{ scale: fileBtnScale }] }}>
                    <TouchableOpacity
                        style={styles.fileClaimBtn}
                        onPress={() => { resetForm(); setShowForm(true); }}
                        onPressIn={fileBtnIn}
                        onPressOut={fileBtnOut}
                        activeOpacity={1}
                    >
                        <LinearGradient
                            colors={[COLORS.primary, COLORS.primaryLight]}
                            style={styles.fileClaimGradient}
                            start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }}
                        >
                            <Ionicons name="add-circle-outline" size={20} color={COLORS.white} />
                            <Text style={styles.fileClaimText}>{t('fileClaim')}</Text>
                        </LinearGradient>
                    </TouchableOpacity>
                </Animated.View>
            </View>

            {/* Claims List */}
            <FlatList
                data={claims}
                keyExtractor={(item) => item.claim_id.toString()}
                renderItem={renderClaim}
                contentContainerStyle={styles.list}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.primary]} />}
                ListEmptyComponent={
                    <View style={styles.empty}>
                        <View style={styles.emptyIcon}>
                            <Ionicons name="shield-outline" size={48} color={COLORS.textMuted} />
                        </View>
                        <Text style={styles.emptyTitle}>{t('noClaimsYet')}</Text>
                        <Text style={styles.emptyText}>{t('noClaimsSub')}</Text>
                    </View>
                }
            />

            {/* ── File a Claim Modal ─────────────────────────── */}
            <Modal visible={showForm} animationType="slide" transparent>
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <ScrollView showsVerticalScrollIndicator={false}>
                            <View style={styles.modalHeader}>
                                <Text style={styles.modalTitle}>{t('fileClaim')}</Text>
                                <TouchableOpacity onPress={() => setShowForm(false)} style={styles.closeBtn}>
                                    <Ionicons name="close" size={24} color={COLORS.textPrimary} />
                                </TouchableOpacity>
                            </View>
                            <Text style={styles.modalSubtitle}>{t('fileClaimSub')}</Text>

                            {/* Select Verified Report */}
                            <Text style={styles.formLabel}>{t('verifiedReport')} *</Text>
                            {verifiedReports.length === 0 ? (
                                <View style={styles.noReportsCard}>
                                    <Ionicons name="information-circle-outline" size={24} color={COLORS.info} />
                                    <Text style={styles.noReportsText}>{t('noVerifiedReports')}</Text>
                                </View>
                            ) : (
                                <TouchableOpacity style={styles.pickerBtn} onPress={() => setShowReportPicker(true)}>
                                    <Ionicons name="document-text-outline" size={18} color={COLORS.textMuted} />
                                    <Text style={[styles.pickerText, selectedReport && { color: COLORS.textPrimary }]}>
                                        {selectedReport ? `Report #${selectedReport.report_id} — ${selectedReport.field_location || 'No location'}` : t('selectReport')}
                                    </Text>
                                    <Ionicons name="chevron-down" size={18} color={COLORS.textMuted} />
                                </TouchableOpacity>
                            )}

                            {/* Damage Type */}
                            <Text style={styles.formLabel}>{t('damageType')} *</Text>
                            <TouchableOpacity style={styles.pickerBtn} onPress={() => setShowDamagePicker(true)}>
                                <Ionicons name="bug-outline" size={18} color={COLORS.textMuted} />
                                <Text style={[styles.pickerText, damageType && { color: COLORS.textPrimary }]}>
                                    {damageType || t('selectDamageType')}
                                </Text>
                                <Ionicons name="chevron-down" size={18} color={COLORS.textMuted} />
                            </TouchableOpacity>

                            {/* RSBSA Number */}
                            <Text style={styles.formLabel}>{t('rsbsaNumber')}</Text>
                            <View style={styles.inputContainer}>
                                <Ionicons name="card-outline" size={18} color={COLORS.textMuted} />
                                <TextInput style={styles.input} placeholder="e.g. 01-36-04-045-000001" placeholderTextColor={COLORS.textMuted} value={rsbsaNum} onChangeText={setRsbsaNum} />
                            </View>

                            {/* Policy Number */}
                            <Text style={styles.formLabel}>{t('policyNumber')}</Text>
                            <View style={styles.inputContainer}>
                                <Ionicons name="receipt-outline" size={18} color={COLORS.textMuted} />
                                <TextInput style={styles.input} placeholder="e.g. PCIC-2026-00001" placeholderTextColor={COLORS.textMuted} value={policyNum} onChangeText={setPolicyNum} />
                            </View>

                            {/* Info Box */}
                            <View style={styles.infoBox}>
                                <Ionicons name="information-circle" size={18} color={COLORS.info} />
                                <Text style={styles.infoText}>{t('claimInfoNote')}</Text>
                            </View>

                            {/* Submit */}
                            <TouchableOpacity
                                style={[styles.submitBtn, (loading || !selectedReport || !damageType) && styles.submitBtnDisabled]}
                                onPress={handleSubmit}
                                disabled={loading || !selectedReport || !damageType}
                                activeOpacity={0.8}
                            >
                                <LinearGradient
                                    colors={(!selectedReport || !damageType) ? [COLORS.textMuted, COLORS.textMuted] : [COLORS.primary, COLORS.primaryLight]}
                                    style={styles.submitGradient}
                                >
                                    {loading ? <ActivityIndicator color={COLORS.white} /> : (
                                        <>
                                            <Ionicons name="shield-checkmark-outline" size={20} color={COLORS.white} />
                                            <Text style={styles.submitText}>{t('submitClaim')}</Text>
                                        </>
                                    )}
                                </LinearGradient>
                            </TouchableOpacity>
                        </ScrollView>
                    </View>
                </View>
            </Modal>

            {/* Report Picker Modal */}
            <Modal visible={showReportPicker} animationType="fade" transparent>
                <TouchableOpacity style={styles.pickerOverlay} activeOpacity={1} onPress={() => setShowReportPicker(false)}>
                    <View style={styles.pickerModal}>
                        <Text style={styles.pickerTitle}>{t('selectReport')}</Text>
                        <ScrollView style={{ maxHeight: 300 }}>
                            {verifiedReports.map((r: any) => (
                                <TouchableOpacity
                                    key={r.report_id}
                                    style={[styles.pickerItem, selectedReport?.report_id === r.report_id && styles.pickerItemActive]}
                                    onPress={() => { setSelectedReport(r); setShowReportPicker(false); }}
                                >
                                    <View style={{ flex: 1 }}>
                                        <Text style={styles.pickerItemTitle}>Report #{r.report_id}</Text>
                                        <Text style={styles.pickerItemSub}>{r.field_location || 'No location'} • {new Date(r.reported_at).toLocaleDateString()}</Text>
                                    </View>
                                    {selectedReport?.report_id === r.report_id && <Ionicons name="checkmark-circle" size={20} color={COLORS.primary} />}
                                </TouchableOpacity>
                            ))}
                        </ScrollView>
                    </View>
                </TouchableOpacity>
            </Modal>

            {/* Damage Type Picker Modal */}
            <Modal visible={showDamagePicker} animationType="fade" transparent>
                <TouchableOpacity style={styles.pickerOverlay} activeOpacity={1} onPress={() => setShowDamagePicker(false)}>
                    <View style={styles.pickerModal}>
                        <Text style={styles.pickerTitle}>{t('selectDamageType')}</Text>
                        <ScrollView style={{ maxHeight: 350 }}>
                            {DAMAGE_TYPES.map((type) => (
                                <TouchableOpacity
                                    key={type}
                                    style={[styles.pickerItem, damageType === type && styles.pickerItemActive]}
                                    onPress={() => { setDamageType(type); setShowDamagePicker(false); }}
                                >
                                    <Text style={styles.pickerItemTitle}>{type}</Text>
                                    {damageType === type && <Ionicons name="checkmark-circle" size={20} color={COLORS.primary} />}
                                </TouchableOpacity>
                            ))}
                        </ScrollView>
                    </View>
                </TouchableOpacity>
            </Modal>
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: COLORS.background },
    header: {
        paddingTop: 60, paddingBottom: SPACING.lg, paddingHorizontal: SPACING.lg,
        borderBottomLeftRadius: RADIUS.xl, borderBottomRightRadius: RADIUS.xl,
    },
    headerTitle: { fontSize: 24, fontWeight: '800', color: COLORS.white },
    headerSub: { fontSize: 13, color: 'rgba(255,255,255,0.8)', marginTop: 4 },

    actionRow: { paddingHorizontal: SPACING.lg, paddingTop: SPACING.md },
    fileClaimBtn: { borderRadius: RADIUS.md, overflow: 'hidden' },
    fileClaimGradient: {
        flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
        height: 48, gap: 8, borderRadius: RADIUS.md,
    },
    fileClaimText: { color: COLORS.white, fontSize: 15, fontWeight: '700' },

    list: { paddingHorizontal: SPACING.lg, paddingTop: SPACING.md, paddingBottom: SPACING.xxl },
    claimCard: {
        backgroundColor: COLORS.white, borderRadius: RADIUS.lg,
        padding: SPACING.md, marginBottom: SPACING.sm, ...SHADOWS.small,
    },
    claimHeader: {
        flexDirection: 'row', justifyContent: 'space-between',
        alignItems: 'center', marginBottom: SPACING.sm,
    },
    claimIdRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
    claimId: { fontSize: 15, fontWeight: '700', color: COLORS.textPrimary },
    statusBadge: {
        flexDirection: 'row', alignItems: 'center', gap: 4,
        paddingHorizontal: 10, paddingVertical: 4, borderRadius: RADIUS.full,
    },
    statusText: { fontSize: 11, fontWeight: '700' },

    claimDetail: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 4 },
    detailText: { fontSize: 13, color: COLORS.textSecondary, flex: 1 },

    claimFooter: {
        flexDirection: 'row', justifyContent: 'space-between',
        alignItems: 'center', marginTop: SPACING.sm,
        paddingTop: SPACING.sm, borderTopWidth: 1, borderTopColor: COLORS.border,
    },
    dateText: { fontSize: 11, color: COLORS.textMuted },
    rsbsaTag: { fontSize: 11, fontWeight: '600', color: COLORS.info },

    empty: { alignItems: 'center', marginTop: 60 },
    emptyIcon: {
        width: 80, height: 80, borderRadius: 40,
        backgroundColor: COLORS.primaryFaded, justifyContent: 'center', alignItems: 'center',
        marginBottom: SPACING.md,
    },
    emptyTitle: { fontSize: 18, fontWeight: '700', color: COLORS.textPrimary },
    emptyText: { fontSize: 14, color: COLORS.textMuted, marginTop: 4, textAlign: 'center', paddingHorizontal: 40 },

    // Modal
    modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
    modalContent: {
        backgroundColor: COLORS.white, borderTopLeftRadius: RADIUS.xl, borderTopRightRadius: RADIUS.xl,
        padding: SPACING.lg, maxHeight: '90%',
    },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
    modalTitle: { fontSize: 20, fontWeight: '800', color: COLORS.textPrimary },
    closeBtn: {
        width: 36, height: 36, borderRadius: 18,
        backgroundColor: COLORS.background, justifyContent: 'center', alignItems: 'center',
    },
    modalSubtitle: { fontSize: 13, color: COLORS.textMuted, marginTop: 4, marginBottom: SPACING.lg },

    formLabel: { fontSize: 13, fontWeight: '600', color: COLORS.textSecondary, marginBottom: SPACING.xs, marginTop: SPACING.md },
    pickerBtn: {
        flexDirection: 'row', alignItems: 'center', gap: 8,
        backgroundColor: COLORS.background, borderRadius: RADIUS.md,
        paddingHorizontal: SPACING.md, height: 48,
        borderWidth: 1, borderColor: COLORS.border,
    },
    pickerText: { flex: 1, fontSize: 14, color: COLORS.textMuted },
    inputContainer: {
        flexDirection: 'row', alignItems: 'center', gap: 8,
        backgroundColor: COLORS.background, borderRadius: RADIUS.md,
        paddingHorizontal: SPACING.md, height: 48,
        borderWidth: 1, borderColor: COLORS.border,
    },
    input: { flex: 1, fontSize: 14, color: COLORS.textPrimary },

    noReportsCard: {
        flexDirection: 'row', alignItems: 'flex-start', gap: 10,
        backgroundColor: '#E3F2FD', borderRadius: RADIUS.md, padding: SPACING.md,
    },
    noReportsText: { flex: 1, fontSize: 13, color: '#1565C0', lineHeight: 18 },

    infoBox: {
        flexDirection: 'row', alignItems: 'flex-start', gap: 8,
        backgroundColor: '#E3F2FD', borderRadius: RADIUS.md, padding: SPACING.md,
        marginTop: SPACING.lg,
    },
    infoText: { flex: 1, fontSize: 12, color: '#1565C0', lineHeight: 17 },

    submitBtn: { marginTop: SPACING.lg, borderRadius: RADIUS.md, overflow: 'hidden', marginBottom: SPACING.lg },
    submitBtnDisabled: { opacity: 0.6 },
    submitGradient: {
        flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
        height: 52, gap: 8,
    },
    submitText: { color: COLORS.white, fontSize: 16, fontWeight: '700' },

    // Picker Modals
    pickerOverlay: {
        flex: 1, backgroundColor: 'rgba(0,0,0,0.4)',
        justifyContent: 'center', paddingHorizontal: SPACING.lg,
    },
    pickerModal: {
        backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: SPACING.lg,
        ...SHADOWS.large,
    },
    pickerTitle: { fontSize: 18, fontWeight: '700', color: COLORS.textPrimary, marginBottom: SPACING.md },
    pickerItem: {
        flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
        padding: SPACING.md, borderRadius: RADIUS.md, marginBottom: 4,
    },
    pickerItemActive: { backgroundColor: COLORS.primaryFaded },
    pickerItemTitle: { fontSize: 14, fontWeight: '600', color: COLORS.textPrimary },
    pickerItemSub: { fontSize: 12, color: COLORS.textMuted, marginTop: 2 },
});
